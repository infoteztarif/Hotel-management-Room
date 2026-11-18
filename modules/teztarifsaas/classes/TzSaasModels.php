<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class TzSaasTenant extends ObjectModel
{
    public $id_shop;
    public $company_name;
    public $slug;
    public $status = 'active';
    public $date_add;
    public $date_upd;

    public static $definition = array(
        'table' => 'tz_saas_tenant',
        'primary' => 'id_tenant',
        'fields' => array(
            'id_shop' => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedId'),
            'company_name' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isGenericName', 'size' => 128),
            'slug' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isLinkRewrite', 'size' => 96),
            'status' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isGenericName', 'size' => 24),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'copy_post' => false),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'copy_post' => false),
        ),
    );

    public static function getByShop($idShop)
    {
        $id = (int) Db::getInstance()->getValue(
            'SELECT `id_tenant` FROM `'._DB_PREFIX_.'tz_saas_tenant` WHERE `id_shop` = '.(int) $idShop
        );

        return $id ? new self($id) : false;
    }

    public static function getChoices()
    {
        return Db::getInstance()->executeS(
            'SELECT `id_tenant`, `company_name` FROM `'._DB_PREFIX_.'tz_saas_tenant` ORDER BY `company_name`'
        );
    }
}

class TzSaasPlan extends ObjectModel
{
    public $code;
    public $name;
    public $currency = 'INR';
    public $price_monthly = 0;
    public $user_limit = 5;
    public $property_limit = 1;
    public $ai_monthly_limit = 0;
    public $active = 1;
    public $date_add;
    public $date_upd;

    public static $definition = array(
        'table' => 'tz_saas_plan',
        'primary' => 'id_plan',
        'fields' => array(
            'code' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isLinkRewrite', 'size' => 48),
            'name' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isGenericName', 'size' => 96),
            'currency' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isGenericName', 'size' => 3),
            'price_monthly' => array('type' => self::TYPE_FLOAT, 'required' => true, 'validate' => 'isPrice'),
            'user_limit' => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedInt'),
            'property_limit' => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedInt'),
            'ai_monthly_limit' => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedInt'),
            'active' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'copy_post' => false),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'copy_post' => false),
        ),
    );

    public static function getChoices()
    {
        return Db::getInstance()->executeS(
            'SELECT `id_plan`, `name` FROM `'._DB_PREFIX_.'tz_saas_plan` WHERE `active` = 1 ORDER BY `price_monthly`'
        );
    }
}

class TzSaasSubscription extends ObjectModel
{
    public $id_tenant;
    public $id_plan;
    public $status = 'trialing';
    public $trial_ends_at;
    public $current_period_start;
    public $current_period_end;
    public $provider;
    public $provider_subscription_id;
    public $date_add;
    public $date_upd;

    public static $definition = array(
        'table' => 'tz_saas_subscription',
        'primary' => 'id_subscription',
        'fields' => array(
            'id_tenant' => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedId'),
            'id_plan' => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedId'),
            'status' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isGenericName', 'size' => 24),
            'trial_ends_at' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'allow_null' => true),
            'current_period_start' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'allow_null' => true),
            'current_period_end' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'allow_null' => true),
            'provider' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 32, 'allow_null' => true),
            'provider_subscription_id' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 128, 'allow_null' => true),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'copy_post' => false),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'copy_post' => false),
        ),
    );

}

class TzSaasTenantHotel extends ObjectModel
{
    public $id_tenant;
    public $id_hotel;
    public $date_add;

    public static $definition = array(
        'table' => 'tz_saas_tenant_hotel',
        'primary' => 'id_tenant_hotel',
        'fields' => array(
            'id_tenant' => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedId'),
            'id_hotel' => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedId'),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'copy_post' => false),
        ),
    );

    public function add($autodate = true, $nullValues = false)
    {
        $subscription = TzSaasService::getActiveSubscription((int) $this->id_tenant);
        if (!$subscription) {
            return false;
        }
        $limit = (int) Db::getInstance()->getValue(
            'SELECT p.`property_limit` FROM `'._DB_PREFIX_.'tz_saas_plan` p
             WHERE p.`id_plan` = '.(int) $subscription['id_plan']
        );
        $used = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `'._DB_PREFIX_.'tz_saas_tenant_hotel` WHERE `id_tenant` = '.(int) $this->id_tenant
        );
        return (!$limit || $used < $limit) && parent::add($autodate, $nullValues);
    }
}

class TzSaasEntitlement extends ObjectModel
{
    public $id_plan;
    public $feature_key;
    public $enabled = 1;
    public $limit_value;
    public $date_add;
    public $date_upd;

    public static $definition = array(
        'table' => 'tz_saas_entitlement',
        'primary' => 'id_entitlement',
        'fields' => array(
            'id_plan' => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedId'),
            'feature_key' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isLinkRewrite', 'size' => 64),
            'enabled' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
            'limit_value' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'allow_null' => true),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'copy_post' => false),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'copy_post' => false),
        ),
    );
}

class TzSaasRecommendation extends ObjectModel
{
    public $id_tenant;
    public $id_hotel;
    public $recommendation_type;
    public $severity;
    public $title;
    public $evidence_json;
    public $confidence;
    public $status = 'open';
    public $date_add;
    public $date_upd;

    public static $definition = array(
        'table' => 'tz_saas_recommendation',
        'primary' => 'id_recommendation',
        'fields' => array(
            'id_tenant' => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedId'),
            'id_hotel' => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedId'),
            'recommendation_type' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isLinkRewrite', 'size' => 64),
            'severity' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isGenericName', 'size' => 16),
            'title' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isGenericName', 'size' => 255),
            'evidence_json' => array('type' => self::TYPE_HTML, 'validate' => 'isCleanHtml'),
            'confidence' => array('type' => self::TYPE_FLOAT, 'validate' => 'isFloat'),
            'status' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isGenericName', 'size' => 24),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'copy_post' => false),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'copy_post' => false),
        ),
    );
}
