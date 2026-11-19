<?php

require_once dirname(__FILE__).'/TzSaasAdminBaseController.php';

class AdminTzSaasTenantHotelsController extends TzSaasAdminBaseController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'tz_saas_tenant_hotel';
        $this->className = 'TzSaasTenantHotel';
        $this->identifier = 'id_tenant_hotel';
        $this->_select = 't.`company_name`, hl.`hotel_name`';
        $this->_join = ' LEFT JOIN `'._DB_PREFIX_.'tz_saas_tenant` t ON t.`id_tenant` = a.`id_tenant`
            LEFT JOIN `'._DB_PREFIX_.'htl_branch_info_lang` hl ON hl.`id` = a.`id_hotel` AND hl.`id_lang` = '.(int) Context::getContext()->language->id;
        $this->fields_list = array('company_name' => array('title' => 'Company', 'filter_key' => 't!company_name'),
            'hotel_name' => array('title' => 'Property', 'filter_key' => 'hl!hotel_name'), 'date_add' => array('title' => 'Mapped', 'type' => 'datetime'));
        parent::__construct();
    }

    public function renderForm()
    {
        $this->fields_form = array('legend' => array('title' => 'Property ownership mapping'), 'input' => array(
            array('type' => 'select', 'label' => 'Company', 'name' => 'id_tenant', 'required' => true,
                'options' => array('query' => $this->tenantOptions(), 'id' => 'id_tenant', 'name' => 'company_name')),
            array('type' => 'select', 'label' => 'Property', 'name' => 'id_hotel', 'required' => true,
                'options' => array('query' => $this->hotelOptions(), 'id' => 'id_hotel', 'name' => 'hotel_name')),
        ), 'submit' => array('title' => 'Save'));
        return parent::renderForm();
    }
}
