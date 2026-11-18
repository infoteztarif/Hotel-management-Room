<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

abstract class TzSaasAdminBaseController extends ModuleAdminController
{
    protected $superAdminOnly = true;

    public function init()
    {
        parent::init();
        if ($this->superAdminOnly && !$this->context->employee->isSuperAdmin()) {
            TzSaasService::deny('Only the SaaS Super Admin can manage this section.');
        }
    }

    protected function tenantOptions()
    {
        return TzSaasTenant::getChoices();
    }

    protected function planOptions()
    {
        return TzSaasPlan::getChoices();
    }

    protected function hotelOptions()
    {
        return Db::getInstance()->executeS(
            'SELECT h.`id` AS `id_hotel`, COALESCE(hl.`hotel_name`, CONCAT(\'Property #\', h.`id`)) AS `hotel_name`
             FROM `'._DB_PREFIX_.'htl_branch_info` h
             LEFT JOIN `'._DB_PREFIX_.'htl_branch_info_lang` hl ON hl.`id` = h.`id`
                AND hl.`id_lang` = '.(int) $this->context->language->id.' ORDER BY `hotel_name`'
        );
    }
}
