<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__).'/classes/TzSaasModels.php';
require_once dirname(__FILE__).'/classes/TzSaasService.php';

class TeztarifSaas extends Module
{
    public function __construct()
    {
        $this->name = 'teztarifsaas';
        $this->tab = 'administration';
        $this->version = '0.1.0';
        $this->author = 'Teztarif';
        $this->bootstrap = true;
        $this->need_instance = 0;
        parent::__construct();

        $this->displayName = $this->l('Teztarif SaaS Control');
        $this->description = $this->l('Company isolation, plans, subscriptions, module licensing, AI quotas and operational recommendations.');
    }

    public function install()
    {
        return parent::install()
            && $this->createTables()
            && $this->seedDefaults()
            && $this->installTabs()
            && $this->registerHook(array('actionDispatcher', 'displayAdminAfterHeader'));
    }

    public function uninstall()
    {
        // Business and audit data is intentionally retained. Destructive removal
        // must be an explicit database migration, never a module uninstall side effect.
        return $this->uninstallTabs() && parent::uninstall();
    }

    protected function createTables()
    {
        $queries = array(
            'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'tz_saas_tenant` (
                `id_tenant` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_shop` INT UNSIGNED NOT NULL,
                `company_name` VARCHAR(128) NOT NULL, `slug` VARCHAR(96) NOT NULL, `status` VARCHAR(24) NOT NULL DEFAULT \'active\',
                `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_tenant`), UNIQUE KEY `uniq_tenant_shop` (`id_shop`), UNIQUE KEY `uniq_tenant_slug` (`slug`)
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'tz_saas_plan` (
                `id_plan` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(48) NOT NULL, `name` VARCHAR(96) NOT NULL,
                `currency` CHAR(3) NOT NULL DEFAULT \'INR\', `price_monthly` DECIMAL(20,6) NOT NULL DEFAULT 0,
                `user_limit` INT UNSIGNED NOT NULL DEFAULT 5, `property_limit` INT UNSIGNED NOT NULL DEFAULT 1,
                `ai_monthly_limit` INT UNSIGNED NOT NULL DEFAULT 0, `active` TINYINT(1) NOT NULL DEFAULT 1,
                `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_plan`), UNIQUE KEY `uniq_plan_code` (`code`)
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'tz_saas_subscription` (
                `id_subscription` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_tenant` INT UNSIGNED NOT NULL, `id_plan` INT UNSIGNED NOT NULL,
                `status` VARCHAR(24) NOT NULL, `trial_ends_at` DATETIME NULL, `current_period_start` DATETIME NULL,
                `current_period_end` DATETIME NULL, `provider` VARCHAR(32) NULL, `provider_subscription_id` VARCHAR(128) NULL,
                `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_subscription`), UNIQUE KEY `uniq_subscription_tenant` (`id_tenant`), KEY (`id_plan`)
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'tz_saas_entitlement` (
                `id_entitlement` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_plan` INT UNSIGNED NOT NULL,
                `feature_key` VARCHAR(64) NOT NULL, `enabled` TINYINT(1) NOT NULL DEFAULT 1, `limit_value` INT UNSIGNED NULL,
                `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_entitlement`), UNIQUE KEY `uniq_plan_feature` (`id_plan`, `feature_key`)
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'tz_saas_tenant_hotel` (
                `id_tenant_hotel` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_tenant` INT UNSIGNED NOT NULL,
                `id_hotel` INT UNSIGNED NOT NULL, `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`id_tenant_hotel`), UNIQUE KEY `uniq_tenant_hotel` (`id_tenant`, `id_hotel`),
                UNIQUE KEY `uniq_hotel_owner` (`id_hotel`)
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'tz_saas_ai_usage` (
                `id_usage` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_tenant` INT UNSIGNED NOT NULL,
                `feature_key` VARCHAR(64) NOT NULL, `request_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `token_count` BIGINT UNSIGNED NOT NULL DEFAULT 0, `cost_minor` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `period_start` DATE NOT NULL, `period_end` DATE NOT NULL, `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_usage`), UNIQUE KEY `uniq_ai_period` (`id_tenant`, `feature_key`, `period_start`)
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'tz_saas_audit_event` (
                `id_event` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_tenant` INT UNSIGNED NOT NULL,
                `id_employee` INT UNSIGNED NOT NULL DEFAULT 0, `event_type` VARCHAR(96) NOT NULL,
                `entity_type` VARCHAR(64) NULL, `entity_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `ip_hash` CHAR(64) NOT NULL, `metadata_json` TEXT NULL, `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`id_event`), KEY `idx_tenant_date` (`id_tenant`, `date_add`)
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'tz_saas_recommendation` (
                `id_recommendation` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_tenant` INT UNSIGNED NOT NULL,
                `id_hotel` INT UNSIGNED NOT NULL, `recommendation_type` VARCHAR(64) NOT NULL, `severity` VARCHAR(16) NOT NULL,
                `title` VARCHAR(255) NOT NULL, `evidence_json` TEXT NULL, `confidence` DECIMAL(5,4) NOT NULL DEFAULT 0,
                `status` VARCHAR(24) NOT NULL DEFAULT \'open\', `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_recommendation`), KEY `idx_tenant_status` (`id_tenant`, `status`)
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4',
        );
        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }
        return true;
    }

    protected function seedDefaults()
    {
        $plans = array(
            array('basic', 'Basic', 1999, 5, 1, 0, array('core', 'booking')),
            array('pro', 'Pro', 4999, 25, 5, 500, array('core', 'booking', 'reports', 'payments', 'ai-operations')),
            array('max', 'Max', 9999, 100, 25, 5000, array('core', 'booking', 'reports', 'payments', 'channel-manager', 'ai-operations')),
        );
        foreach ($plans as $planData) {
            $idPlan = (int) Db::getInstance()->getValue(
                'SELECT `id_plan` FROM `'._DB_PREFIX_.'tz_saas_plan` WHERE `code` = \''.pSQL($planData[0]).'\''
            );
            if (!$idPlan) {
                $plan = new TzSaasPlan();
                $plan->code = $planData[0];
                $plan->name = $planData[1];
                $plan->currency = 'INR';
                $plan->price_monthly = $planData[2];
                $plan->user_limit = $planData[3];
                $plan->property_limit = $planData[4];
                $plan->ai_monthly_limit = $planData[5];
                $plan->active = 1;
                if (!$plan->add()) {
                    return false;
                }
                $idPlan = (int) $plan->id;
            }
            foreach ($planData[6] as $feature) {
                Db::getInstance()->execute(
                    'INSERT IGNORE INTO `'._DB_PREFIX_.'tz_saas_entitlement`
                    (`id_plan`, `feature_key`, `enabled`, `date_add`, `date_upd`)
                    VALUES ('.$idPlan.', \''.pSQL($feature).'\', 1, NOW(), NOW())'
                );
            }
        }

        $idShop = (int) Configuration::get('PS_SHOP_DEFAULT');
        $tenant = TzSaasTenant::getByShop($idShop);
        if (!$tenant) {
            $tenant = new TzSaasTenant();
            $tenant->id_shop = $idShop;
            $tenant->company_name = Configuration::get('PS_SHOP_NAME') ?: 'Existing workspace';
            $tenant->slug = 'existing-workspace';
            $tenant->status = 'active';
            if (!$tenant->add()) {
                return false;
            }
        }
        $maxPlan = (int) Db::getInstance()->getValue('SELECT `id_plan` FROM `'._DB_PREFIX_.'tz_saas_plan` WHERE `code` = \'max\'');
        if (!TzSaasService::getActiveSubscription($tenant->id)) {
            $subscription = new TzSaasSubscription();
            $subscription->id_tenant = (int) $tenant->id;
            $subscription->id_plan = $maxPlan;
            $subscription->status = 'active';
            $subscription->current_period_start = date('Y-m-d H:i:s');
            if (!$subscription->add()) {
                return false;
            }
        }
        Db::getInstance()->execute(
            'INSERT IGNORE INTO `'._DB_PREFIX_.'tz_saas_tenant_hotel` (`id_tenant`, `id_hotel`, `date_add`)
             SELECT '.(int) $tenant->id.', `id`, NOW() FROM `'._DB_PREFIX_.'htl_branch_info`'
        );
        return true;
    }

    protected function installTabs()
    {
        $parent = new Tab();
        $parent->class_name = 'AdminTzSaas';
        $parent->id_parent = 0;
        $parent->module = $this->name;
        foreach (Language::getLanguages(false) as $language) {
            $parent->name[$language['id_lang']] = $this->l('SaaS Control');
        }
        if (!$parent->add()) {
            return false;
        }
        $tabs = array(
            'AdminTzSaasDashboard' => 'Dashboard',
            'AdminTzSaasTenants' => 'Companies',
            'AdminTzSaasPlans' => 'Plans',
            'AdminTzSaasSubscriptions' => 'Subscriptions',
            'AdminTzSaasEntitlements' => 'Module licensing',
            'AdminTzSaasTenantHotels' => 'Property mapping',
            'AdminTzSaasRecommendations' => 'Smart operations',
        );
        foreach ($tabs as $className => $name) {
            $tab = new Tab();
            $tab->class_name = $className;
            $tab->id_parent = (int) $parent->id;
            $tab->module = $this->name;
            foreach (Language::getLanguages(false) as $language) {
                $tab->name[$language['id_lang']] = $this->l($name);
            }
            if (!$tab->add()) {
                return false;
            }
        }
        return true;
    }

    protected function uninstallTabs()
    {
        $classes = array('AdminTzSaasDashboard', 'AdminTzSaasTenants', 'AdminTzSaasPlans',
            'AdminTzSaasSubscriptions', 'AdminTzSaasEntitlements', 'AdminTzSaasTenantHotels',
            'AdminTzSaasRecommendations', 'AdminTzSaas');
        foreach ($classes as $className) {
            $idTab = (int) Tab::getIdFromClassName($className);
            if ($idTab && !(new Tab($idTab))->delete()) {
                return false;
            }
        }
        return true;
    }

    public function hookActionDispatcher($params)
    {
        if (empty($params['controller_type']) || $params['controller_type'] !== Dispatcher::FC_ADMIN) {
            return;
        }
        $context = Context::getContext();
        if (!isset($context->employee->id) || !$context->employee->id || $context->employee->isSuperAdmin()) {
            return;
        }
        $controllerClass = isset($params['controller_class']) ? $params['controller_class'] : '';
        if (strpos($controllerClass, 'AdminLogin') === 0) {
            return;
        }
        $tenant = TzSaasService::getCurrentTenant();
        $subscription = $tenant ? TzSaasService::getActiveSubscription($tenant->id) : false;
        if (!TzSaasService::isSubscriptionUsable($tenant, $subscription)) {
            TzSaasService::deny('Your company subscription is inactive or expired. Contact the SaaS administrator.');
        }
        $feature = TzSaasService::getControllerFeature($controllerClass);
        if (!TzSaasService::hasFeature($tenant->id, $feature)) {
            TzSaasService::deny('This module is not included in your company plan.');
        }
        foreach (array('AdminShop', 'AdminShopGroup', 'AdminProfiles', 'AdminAccess') as $restrictedController) {
            if (strpos($controllerClass, $restrictedController) === 0) {
                TzSaasService::deny('This global configuration is restricted to the SaaS Super Admin.');
            }
        }
        TzSaasService::assertRequestScope($tenant->id);
    }

    public function hookDisplayAdminAfterHeader()
    {
        $tenant = TzSaasService::getCurrentTenant();
        if (!$tenant) {
            return '';
        }
        $subscription = TzSaasService::getActiveSubscription($tenant->id);
        if (!$subscription) {
            return '<div class="alert alert-danger">'.$this->l('No active SaaS subscription is assigned to this company.').'</div>';
        }
        $end = $subscription['status'] === 'trialing' ? $subscription['trial_ends_at'] : $subscription['current_period_end'];
        if ($end && strtotime($end) < strtotime('+7 days')) {
            return '<div class="alert alert-warning">'.sprintf($this->l('Your %s plan expires on %s.'),
                Tools::safeOutput($subscription['plan_name']), Tools::displayDate($end, null, true)).'</div>';
        }
        return '';
    }

    public function hookRegisterCronTasks()
    {
        return array(array(
            'task_name' => 'teztarif_saas_recommendations',
            'description' => 'Refresh tenant-safe operational recommendations',
            'cron_expression' => '*/30 * * * *',
            'callback' => 'runRecommendationsRefresh',
        ));
    }

    public function runRecommendationsRefresh()
    {
        $tenants = TzSaasTenant::getChoices();
        foreach ($tenants as $tenant) {
            TzSaasService::refreshRecommendations((int) $tenant['id_tenant']);
        }
        return true;
    }
}
