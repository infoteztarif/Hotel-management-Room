<?php

require_once dirname(__FILE__).'/TzSaasAdminBaseController.php';

class AdminTzSaasRecommendationsController extends TzSaasAdminBaseController
{
    protected $superAdminOnly = false;

    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'tz_saas_recommendation';
        $this->className = 'TzSaasRecommendation';
        $this->identifier = 'id_recommendation';
        $this->list_no_link = true;
        $this->_select = 't.`company_name`, hl.`hotel_name`';
        $this->_join = ' LEFT JOIN `'._DB_PREFIX_.'tz_saas_tenant` t ON t.`id_tenant` = a.`id_tenant`
            LEFT JOIN `'._DB_PREFIX_.'htl_branch_info_lang` hl ON hl.`id` = a.`id_hotel` AND hl.`id_lang` = '.(int) Context::getContext()->language->id;
        if (isset(Context::getContext()->employee) && !Context::getContext()->employee->isSuperAdmin()) {
            $tenant = TzSaasService::getCurrentTenant();
            $this->_where = ' AND a.`id_tenant` = '.($tenant ? (int) $tenant->id : 0);
        }
        $this->_orderBy = 'date_add';
        $this->_orderWay = 'DESC';
        $this->fields_list = array(
            'severity' => array('title' => 'Priority'), 'company_name' => array('title' => 'Company', 'filter_key' => 't!company_name'),
            'hotel_name' => array('title' => 'Property', 'filter_key' => 'hl!hotel_name'),
            'recommendation_type' => array('title' => 'Type'), 'title' => array('title' => 'Recommended action'),
            'confidence' => array('title' => 'Confidence'), 'status' => array('title' => 'Status'),
            'date_add' => array('title' => 'Detected', 'type' => 'datetime'),
        );
        parent::__construct();
    }

    public function initPageHeaderToolbar()
    {
        parent::initPageHeaderToolbar();
        $this->page_header_toolbar_btn['refresh_recommendations'] = array(
            'href' => self::$currentIndex.'&token='.$this->token.'&refreshRecommendations=1',
            'desc' => 'Refresh evidence-backed recommendations', 'icon' => 'process-icon-refresh',
        );
    }

    public function postProcess()
    {
        if (Tools::isSubmit('refreshRecommendations')) {
            $tenant = TzSaasService::getCurrentTenant();
            if ($this->context->employee->isSuperAdmin() && ($requested = (int) Tools::getValue('id_tenant'))) {
                $tenant = new TzSaasTenant($requested);
            }
            if (!$tenant || !Validate::isLoadedObject($tenant)) {
                $this->errors[] = 'Select a valid company workspace before refreshing.';
            } else {
                $count = TzSaasService::refreshRecommendations((int) $tenant->id);
                $this->confirmations[] = sprintf('%d operational recommendations refreshed.', $count);
            }
        }
        parent::postProcess();
    }

    public function initToolbar()
    {
        parent::initToolbar();
        unset($this->toolbar_btn['new']);
    }
}
