<?php
/**
 * Adds the composite indexes used by availability, booking and operations
 * queries. Existing installations receive the same indexes as fresh ones.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_8_1($module)
{
    $upgrade = new UpgradeHotelReservationSystem181($module);

    return $upgrade->run();
}

class UpgradeHotelReservationSystem181
{
    private $module;

    public function __construct($module)
    {
        $this->module = $module;
    }

    public function run()
    {
        $indexes = array(
            'maintenance_access' => array(
                'idx_maintenance_email_date' => array('email', 'date_add'),
                'idx_maintenance_ip_date' => array('ip_address', 'date_add'),
            ),
            'htl_room_type' => array(
                'idx_room_type_hotel_product' => array('id_hotel', 'id_product'),
            ),
            'htl_room_information' => array(
                'idx_hotel_product' => array('id_hotel', 'id_product'),
            ),
            'htl_cart_booking_data' => array(
                'idx_cart' => array('id_cart'),
                'idx_cart_room_dates' => array('id_room', 'date_from', 'date_to'),
                'idx_cart_hotel_dates' => array('id_hotel', 'date_from', 'date_to'),
            ),
            'htl_booking_detail' => array(
                'idx_booking_hotel_dates' => array('id_hotel', 'date_from', 'date_to'),
                'idx_booking_room_dates' => array('id_room', 'date_from', 'date_to'),
                'idx_booking_order' => array('id_order'),
                'idx_booking_customer' => array('id_customer'),
                'idx_booking_backorder_checkout' => array('is_back_order', 'date_to'),
            ),
        );

        foreach ($indexes as $table => $tableIndexes) {
            foreach ($tableIndexes as $index => $columns) {
                if (!$this->addIndexIfMissing($table, $index, $columns)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function addIndexIfMissing($table, $index, array $columns)
    {
        $fullTable = _DB_PREFIX_.$table;
        $exists = Db::getInstance()->getValue(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = '".pSQL($fullTable)."'
               AND INDEX_NAME = '".pSQL($index)."'"
        );

        if ($exists) {
            return true;
        }

        $quotedColumns = array();
        foreach ($columns as $column) {
            $quotedColumns[] = '`'.bqSQL($column).'`';
        }

        return Db::getInstance()->execute(
            'ALTER TABLE `'.bqSQL($fullTable).'` ADD INDEX `'.bqSQL($index).'` ('.implode(', ', $quotedColumns).')'
        );
    }
}
