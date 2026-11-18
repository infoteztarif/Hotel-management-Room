<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class TzSaasService
{
    const FEATURE_CORE = 'core';
    const FEATURE_BOOKING = 'booking';
    const FEATURE_REPORTS = 'reports';
    const FEATURE_PAYMENTS = 'payments';
    const FEATURE_CHANNEL_MANAGER = 'channel-manager';
    const FEATURE_AI_OPERATIONS = 'ai-operations';

    public static function getCurrentTenant()
    {
        $context = Context::getContext();
        if (!isset($context->shop->id)) {
            return false;
        }

        return TzSaasTenant::getByShop((int) $context->shop->id);
    }

    public static function getActiveSubscription($idTenant)
    {
        $row = Db::getInstance()->getRow(
            'SELECT s.*, p.`name` AS plan_name, p.`ai_monthly_limit`
             FROM `'._DB_PREFIX_.'tz_saas_subscription` s
             INNER JOIN `'._DB_PREFIX_.'tz_saas_plan` p ON p.`id_plan` = s.`id_plan`
             WHERE s.`id_tenant` = '.(int) $idTenant.' ORDER BY s.`id_subscription` DESC'
        );

        return $row ?: false;
    }

    public static function isSubscriptionUsable($tenant, $subscription)
    {
        if (!$tenant || $tenant->status !== 'active' || !$subscription) {
            return false;
        }
        if (!in_array($subscription['status'], array('active', 'trialing'), true)) {
            return false;
        }

        $limit = $subscription['status'] === 'trialing'
            ? $subscription['trial_ends_at']
            : $subscription['current_period_end'];

        return empty($limit) || strtotime($limit) >= time();
    }

    public static function hasFeature($idTenant, $featureKey)
    {
        $subscription = self::getActiveSubscription($idTenant);
        if (!$subscription) {
            return false;
        }

        return (bool) Db::getInstance()->getValue(
            'SELECT `enabled` FROM `'._DB_PREFIX_.'tz_saas_entitlement`
             WHERE `id_plan` = '.(int) $subscription['id_plan'].'
             AND `feature_key` = \''.pSQL($featureKey).'\''
        );
    }

    public static function getControllerFeature($controllerClass)
    {
        $map = array(
            self::FEATURE_REPORTS => array('AdminStats', 'AdminHotelReports', 'AdminReferrers'),
            self::FEATURE_PAYMENTS => array('AdminPayment', 'AdminPaypal', 'AdminDuitku', 'AdminOutstanding'),
            self::FEATURE_CHANNEL_MANAGER => array('AdminQloappsChannelManager', 'AdminGoogleHotel'),
            self::FEATURE_AI_OPERATIONS => array('AdminTzSaasRecommendations'),
        );
        foreach ($map as $feature => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (strpos($controllerClass, $prefix) === 0) {
                    return $feature;
                }
            }
        }

        return self::FEATURE_CORE;
    }

    public static function assertRequestScope($idTenant)
    {
        $hotelKeys = array('id_hotel', 'idHotel', 'hotel_id');
        foreach ($hotelKeys as $key) {
            $idHotel = (int) Tools::getValue($key);
            if ($idHotel && !self::tenantOwnsHotel($idTenant, $idHotel)) {
                self::deny('The selected property does not belong to your company.');
            }
        }

        $idBooking = (int) Tools::getValue('id_htl_booking');
        if ($idBooking) {
            $idHotel = (int) Db::getInstance()->getValue(
                'SELECT `id_hotel` FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE `id` = '.$idBooking
            );
            if ($idHotel && !self::tenantOwnsHotel($idTenant, $idHotel)) {
                self::deny('The selected booking does not belong to your company.');
            }
        }

        $idOrder = (int) Tools::getValue('id_order');
        if ($idOrder) {
            $tenant = new TzSaasTenant($idTenant);
            $idShop = (int) Db::getInstance()->getValue(
                'SELECT `id_shop` FROM `'._DB_PREFIX_.'orders` WHERE `id_order` = '.$idOrder
            );
            if ($idShop && $idShop !== (int) $tenant->id_shop) {
                self::deny('The selected order does not belong to your company.');
            }
        }

        if (Tools::isSubmit('submitAddemployee') && !(int) Tools::getValue('id_employee')) {
            $tenant = new TzSaasTenant($idTenant);
            $subscription = self::getActiveSubscription($idTenant);
            $limit = $subscription ? (int) Db::getInstance()->getValue(
                'SELECT `user_limit` FROM `'._DB_PREFIX_.'tz_saas_plan` WHERE `id_plan` = '.(int) $subscription['id_plan']
            ) : 0;
            $used = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `'._DB_PREFIX_.'employee_shop` WHERE `id_shop` = '.(int) $tenant->id_shop
            );
            if ($limit > 0 && $used >= $limit) {
                self::deny('Your company has reached the user limit for its plan.');
            }
        }
    }

    public static function tenantOwnsHotel($idTenant, $idHotel)
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT 1 FROM `'._DB_PREFIX_.'tz_saas_tenant_hotel`
             WHERE `id_tenant` = '.(int) $idTenant.' AND `id_hotel` = '.(int) $idHotel
        );
    }

    public static function getTenantHotelIds($idTenant)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT `id_hotel` FROM `'._DB_PREFIX_.'tz_saas_tenant_hotel` WHERE `id_tenant` = '.(int) $idTenant
        );
        return array_map('intval', array_column($rows, 'id_hotel'));
    }

    public static function consumeAiQuota($idTenant, $featureKey, $requestCount = 1, $tokenCount = 0, $costMinor = 0)
    {
        $subscription = self::getActiveSubscription($idTenant);
        if (!$subscription || !self::hasFeature($idTenant, self::FEATURE_AI_OPERATIONS)) {
            return false;
        }

        $periodStart = date('Y-m-01');
        $used = (int) Db::getInstance()->getValue(
            'SELECT COALESCE(SUM(`request_count`), 0) FROM `'._DB_PREFIX_.'tz_saas_ai_usage`
             WHERE `id_tenant` = '.(int) $idTenant.' AND `period_start` = \''.pSQL($periodStart).'\''
        );
        $limit = (int) $subscription['ai_monthly_limit'];
        if ($limit > 0 && ($used + $requestCount) > $limit) {
            return false;
        }

        return Db::getInstance()->execute(
            'INSERT INTO `'._DB_PREFIX_.'tz_saas_ai_usage`
             (`id_tenant`, `feature_key`, `request_count`, `token_count`, `cost_minor`, `period_start`, `period_end`, `date_upd`)
             VALUES ('.(int) $idTenant.', \''.pSQL($featureKey).'\', '.(int) $requestCount.', '.(int) $tokenCount.', '.(int) $costMinor.',
                     \''.pSQL($periodStart).'\', LAST_DAY(\''.pSQL($periodStart).'\'), NOW())
             ON DUPLICATE KEY UPDATE `request_count` = `request_count` + VALUES(`request_count`),
                 `token_count` = `token_count` + VALUES(`token_count`), `cost_minor` = `cost_minor` + VALUES(`cost_minor`),
                 `date_upd` = NOW()'
        );
    }

    public static function audit($idTenant, $eventType, $entityType = '', $entityId = 0, array $metadata = array())
    {
        $context = Context::getContext();
        $employeeId = isset($context->employee->id) ? (int) $context->employee->id : 0;
        $ipHash = hash('sha256', Tools::getRemoteAddr()._COOKIE_KEY_);

        return Db::getInstance()->insert('tz_saas_audit_event', array(
            'id_tenant' => (int) $idTenant,
            'id_employee' => $employeeId,
            'event_type' => pSQL($eventType),
            'entity_type' => pSQL($entityType),
            'entity_id' => (int) $entityId,
            'ip_hash' => pSQL($ipHash),
            'metadata_json' => pSQL(json_encode($metadata)),
            'date_add' => date('Y-m-d H:i:s'),
        ));
    }

    public static function refreshRecommendations($idTenant)
    {
        $hotelIds = self::getTenantHotelIds($idTenant);
        if (!$hotelIds) {
            return 0;
        }
        if (!self::consumeAiQuota($idTenant, 'operations-recommendations')) {
            return 0;
        }
        $ids = implode(',', array_map('intval', $hotelIds));
        Db::getInstance()->delete('tz_saas_recommendation', 'id_tenant = '.(int) $idTenant.' AND status = \'open\'');

        $created = 0;
        $overbookings = Db::getInstance()->executeS(
            'SELECT `id_hotel`, COUNT(*) AS total FROM `'._DB_PREFIX_.'htl_booking_detail`
             WHERE `id_hotel` IN ('.$ids.') AND `is_back_order` = 1 AND `date_to` >= NOW()
             GROUP BY `id_hotel`'
        );
        foreach ($overbookings as $row) {
            $created += self::addRecommendation($idTenant, $row['id_hotel'], 'overbooking-risk', 'critical',
                sprintf('%d future room bookings are currently overbooked', $row['total']), array('overbooked_bookings' => (int) $row['total']), 1.0);
        }

        $unassigned = Db::getInstance()->executeS(
            'SELECT `id_hotel`, COUNT(*) AS total FROM `'._DB_PREFIX_.'htl_booking_detail`
             WHERE `id_hotel` IN ('.$ids.') AND `id_room` = 0 AND `date_from` BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 48 HOUR)
             GROUP BY `id_hotel`'
        );
        foreach ($unassigned as $row) {
            $created += self::addRecommendation($idTenant, $row['id_hotel'], 'room-allocation', 'high',
                sprintf('%d arrivals in the next 48 hours still need a room assignment', $row['total']), array('unassigned_arrivals' => (int) $row['total']), 1.0);
        }

        $payments = Db::getInstance()->executeS(
            'SELECT `id_hotel`, COUNT(*) AS bookings, ROUND(SUM(`total_price_tax_incl` - `total_paid_amount`), 2) AS outstanding
             FROM `'._DB_PREFIX_.'htl_booking_detail`
             WHERE `id_hotel` IN ('.$ids.') AND `date_from` BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 72 HOUR)
             AND `total_paid_amount` < `total_price_tax_incl` GROUP BY `id_hotel`'
        );
        foreach ($payments as $row) {
            $created += self::addRecommendation($idTenant, $row['id_hotel'], 'payment-follow-up', 'medium',
                sprintf('%d near-term bookings have an outstanding balance', $row['bookings']),
                array('bookings' => (int) $row['bookings'], 'outstanding' => (float) $row['outstanding']), 1.0);
        }

        self::audit($idTenant, 'recommendations.refreshed', 'tenant', $idTenant, array('created' => $created));
        return $created;
    }

    protected static function addRecommendation($idTenant, $idHotel, $type, $severity, $title, array $evidence, $confidence)
    {
        $recommendation = new TzSaasRecommendation();
        $recommendation->id_tenant = (int) $idTenant;
        $recommendation->id_hotel = (int) $idHotel;
        $recommendation->recommendation_type = $type;
        $recommendation->severity = $severity;
        $recommendation->title = $title;
        $recommendation->evidence_json = json_encode($evidence);
        $recommendation->confidence = (float) $confidence;
        $recommendation->status = 'open';
        return $recommendation->add() ? 1 : 0;
    }

    public static function deny($message)
    {
        if (!headers_sent()) {
            header('HTTP/1.1 403 Forbidden');
        }
        throw new PrestaShopException(Tools::displayError($message));
    }
}
