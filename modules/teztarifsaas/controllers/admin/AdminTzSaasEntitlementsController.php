<?php

require_once dirname(__FILE__).'/TzSaasAdminBaseController.php';

class AdminTzSaasEntitlementsController extends TzSaasAdminBaseController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'tz_saas_entitlement';
        $this->className = 'TzSaasEntitlement';
        $this->identifier = 'id_entitlement';
        $this->_select = 'p.`name` AS `plan_name`';
        $this->_join = ' LEFT JOIN `'._DB_PREFIX_.'tz_saas_plan` p ON p.`id_plan` = a.`id_plan`';
        $this->fields_list = array('plan_name' => array('title' => 'Plan', 'filter_key' => 'p!name'),
            'feature_key' => array('title' => 'Module key'), 'enabled' => array('title' => 'Enabled', 'type' => 'bool'),
            'limit_value' => array('title' => 'Optional limit'));
        parent::__construct();
    }

    public function renderForm()
    {
        $this->fields_form = array('legend' => array('title' => 'Plan module license'), 'input' => array(
            array('type' => 'select', 'label' => 'Plan', 'name' => 'id_plan', 'required' => true,
                'options' => array('query' => $this->planOptions(), 'id' => 'id_plan', 'name' => 'name')),
            array('type' => 'select', 'label' => 'Module', 'name' => 'feature_key', 'required' => true,
                'options' => array('query' => array(
                    array('id' => 'core', 'name' => 'Core PMS'), array('id' => 'booking', 'name' => 'Booking engine'),
                    array('id' => 'reports', 'name' => 'Reports'), array('id' => 'payments', 'name' => 'Payments'),
                    array('id' => 'channel-manager', 'name' => 'Channel manager'), array('id' => 'ai-operations', 'name' => 'Smart operations'),
                ), 'id' => 'id', 'name' => 'name')),
            array('type' => 'switch', 'label' => 'Enabled', 'name' => 'enabled', 'is_bool' => true, 'values' => array(
                array('id' => 'enabled_on', 'value' => 1, 'label' => 'Yes'), array('id' => 'enabled_off', 'value' => 0, 'label' => 'No'))),
            array('type' => 'text', 'label' => 'Optional limit', 'name' => 'limit_value'),
        ), 'submit' => array('title' => 'Save'));
        return parent::renderForm();
    }
}
