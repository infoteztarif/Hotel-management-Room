<?php

require_once dirname(__FILE__).'/TzSaasAdminBaseController.php';

class AdminTzSaasTenantsController extends TzSaasAdminBaseController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'tz_saas_tenant';
        $this->className = 'TzSaasTenant';
        $this->identifier = 'id_tenant';
        $this->fields_list = array(
            'id_tenant' => array('title' => 'ID', 'class' => 'fixed-width-xs'),
            'company_name' => array('title' => 'Company'),
            'id_shop' => array('title' => 'Shop ID', 'class' => 'fixed-width-sm'),
            'slug' => array('title' => 'Slug'),
            'status' => array('title' => 'Status'),
            'date_add' => array('title' => 'Created', 'type' => 'datetime'),
        );
        parent::__construct();
    }

    public function renderForm()
    {
        $this->fields_form = array('legend' => array('title' => 'Company workspace', 'icon' => 'icon-building'), 'input' => array(
            array('type' => 'select', 'label' => 'QloApps shop', 'name' => 'id_shop', 'required' => true,
                'options' => array('query' => Shop::getShops(false), 'id' => 'id_shop', 'name' => 'name')),
            array('type' => 'text', 'label' => 'Company name', 'name' => 'company_name', 'required' => true),
            array('type' => 'text', 'label' => 'Slug', 'name' => 'slug', 'required' => true),
            array('type' => 'select', 'label' => 'Status', 'name' => 'status', 'options' => array('query' => array(
                array('id' => 'active', 'name' => 'Active'), array('id' => 'suspended', 'name' => 'Suspended'),
            ), 'id' => 'id', 'name' => 'name')),
        ), 'submit' => array('title' => 'Save'));
        return parent::renderForm();
    }
}
