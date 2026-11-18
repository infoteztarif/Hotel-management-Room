<?php

require_once dirname(__FILE__).'/TzSaasAdminBaseController.php';

class AdminTzSaasDashboardController extends TzSaasAdminBaseController
{
    protected $superAdminOnly = false;

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function initContent()
    {
        parent::initContent();
        $isSuperAdmin = $this->context->employee->isSuperAdmin();
        $tenant = TzSaasService::getCurrentTenant();
        $idTenant = $tenant ? (int) $tenant->id : 0;
        $subscription = $idTenant ? TzSaasService::getActiveSubscription($idTenant) : false;

        if ($isSuperAdmin) {
            $metrics = array(
                'Companies' => (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'tz_saas_tenant`'),
                'Active subscriptions' => (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'tz_saas_subscription` WHERE `status` IN (\'active\', \'trialing\')'),
                'Mapped properties' => (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'tz_saas_tenant_hotel`'),
                'Open recommendations' => (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'tz_saas_recommendation` WHERE `status` = \'open\''),
            );
        } else {
            $metrics = array(
                'Plan' => $subscription ? $subscription['plan_name'] : 'Not assigned',
                'Mapped properties' => count(TzSaasService::getTenantHotelIds($idTenant)),
                'Open recommendations' => (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'tz_saas_recommendation` WHERE `id_tenant` = '.$idTenant.' AND `status` = \'open\''),
                'AI requests this month' => (int) Db::getInstance()->getValue('SELECT COALESCE(SUM(`request_count`), 0) FROM `'._DB_PREFIX_.'tz_saas_ai_usage` WHERE `id_tenant` = '.$idTenant.' AND `period_start` = DATE_FORMAT(NOW(), \'%Y-%m-01\')'),
            );
        }

        $html = '<div class="panel"><div class="panel-heading"><i class="icon-building"></i> SaaS Control Dashboard</div>';
        $html .= '<div class="row">';
        foreach ($metrics as $label => $value) {
            $html .= '<div class="col-lg-3 col-md-6"><div class="panel text-center"><h3>'.Tools::safeOutput((string) $value).'</h3><p>'.Tools::safeOutput($label).'</p></div></div>';
        }
        $html .= '</div>';
        $html .= '<div class="alert alert-info">Smart Operations only creates evidence-backed alerts. It never changes room prices, cancels bookings or contacts guests without staff approval.</div>';
        $html .= '<p><a class="btn btn-primary" href="'.$this->context->link->getAdminLink('AdminTzSaasRecommendations').'">Open Smart Operations</a></p></div>';
        $this->content .= $html;
        $this->context->smarty->assign('content', $this->content);
    }
}
