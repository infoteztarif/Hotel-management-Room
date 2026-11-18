<?php

require_once dirname(__FILE__).'/TzSaasAdminBaseController.php';

class AdminTzSaasSubscriptionsController extends TzSaasAdminBaseController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'tz_saas_subscription';
        $this->className = 'TzSaasSubscription';
        $this->identifier = 'id_subscription';
        $this->_select = 't.`company_name`, p.`name` AS `plan_name`';
        $this->_join = ' LEFT JOIN `'._DB_PREFIX_.'tz_saas_tenant` t ON t.`id_tenant` = a.`id_tenant`
            LEFT JOIN `'._DB_PREFIX_.'tz_saas_plan` p ON p.`id_plan` = a.`id_plan`';
        $this->fields_list = array(
            'company_name' => array('title' => 'Company', 'filter_key' => 't!company_name'),
            'plan_name' => array('title' => 'Plan', 'filter_key' => 'p!name'), 'status' => array('title' => 'Status'),
            'trial_ends_at' => array('title' => 'Trial ends', 'type' => 'datetime'),
            'current_period_end' => array('title' => 'Period ends', 'type' => 'datetime'), 'provider' => array('title' => 'Provider'),
        );
        parent::__construct();
    }

    public function renderForm()
    {
        $this->fields_form = array('legend' => array('title' => 'Company subscription'), 'input' => array(
            array('type' => 'select', 'label' => 'Company', 'name' => 'id_tenant', 'required' => true,
                'options' => array('query' => $this->tenantOptions(), 'id' => 'id_tenant', 'name' => 'company_name')),
            array('type' => 'select', 'label' => 'Plan', 'name' => 'id_plan', 'required' => true,
                'options' => array('query' => $this->planOptions(), 'id' => 'id_plan', 'name' => 'name')),
            array('type' => 'select', 'label' => 'Status', 'name' => 'status', 'options' => array('query' => array(
                array('id' => 'trialing', 'name' => 'Trialing'), array('id' => 'active', 'name' => 'Active'),
                array('id' => 'past_due', 'name' => 'Past due'), array('id' => 'canceled', 'name' => 'Canceled'),
            ), 'id' => 'id', 'name' => 'name')),
            array('type' => 'datetime', 'label' => 'Trial ends', 'name' => 'trial_ends_at'),
            array('type' => 'datetime', 'label' => 'Period starts', 'name' => 'current_period_start'),
            array('type' => 'datetime', 'label' => 'Period ends', 'name' => 'current_period_end'),
            array('type' => 'text', 'label' => 'Provider', 'name' => 'provider'),
            array('type' => 'text', 'label' => 'Provider subscription ID', 'name' => 'provider_subscription_id'),
        ), 'submit' => array('title' => 'Save'));
        return parent::renderForm();
    }
}
