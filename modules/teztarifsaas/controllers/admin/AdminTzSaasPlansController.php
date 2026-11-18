<?php

require_once dirname(__FILE__).'/TzSaasAdminBaseController.php';

class AdminTzSaasPlansController extends TzSaasAdminBaseController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'tz_saas_plan';
        $this->className = 'TzSaasPlan';
        $this->identifier = 'id_plan';
        $this->fields_list = array(
            'id_plan' => array('title' => 'ID', 'class' => 'fixed-width-xs'), 'name' => array('title' => 'Plan'),
            'code' => array('title' => 'Code'), 'price_monthly' => array('title' => 'Monthly price', 'type' => 'price'),
            'user_limit' => array('title' => 'Users'), 'property_limit' => array('title' => 'Properties'),
            'ai_monthly_limit' => array('title' => 'AI requests'), 'active' => array('title' => 'Active', 'type' => 'bool'),
        );
        parent::__construct();
    }

    public function renderForm()
    {
        $inputs = array(
            array('type' => 'text', 'label' => 'Code', 'name' => 'code', 'required' => true),
            array('type' => 'text', 'label' => 'Name', 'name' => 'name', 'required' => true),
            array('type' => 'text', 'label' => 'Currency', 'name' => 'currency', 'required' => true),
            array('type' => 'text', 'label' => 'Monthly price', 'name' => 'price_monthly', 'required' => true),
            array('type' => 'text', 'label' => 'User limit', 'name' => 'user_limit', 'required' => true),
            array('type' => 'text', 'label' => 'Property limit', 'name' => 'property_limit', 'required' => true),
            array('type' => 'text', 'label' => 'AI requests/month (0 disables)', 'name' => 'ai_monthly_limit', 'required' => true),
            array('type' => 'switch', 'label' => 'Active', 'name' => 'active', 'is_bool' => true, 'values' => array(
                array('id' => 'active_on', 'value' => 1, 'label' => 'Yes'), array('id' => 'active_off', 'value' => 0, 'label' => 'No'))),
        );
        $this->fields_form = array('legend' => array('title' => 'Subscription plan'), 'input' => $inputs, 'submit' => array('title' => 'Save'));
        return parent::renderForm();
    }
}
