<?php

class ModelExtensionPaymentCryptoMerchant extends Model{
    
    public function install(){
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cryptomerchant_order` (
                `cryptomerchant_order_id` INT(11) NOT NULL AUTO_INCREMENT,
                `order_id` INT(11) NOT NULL,
                `buu_invoice_id` VARCHAR(120),
                `token` VARCHAR(100) NOT NULL,
                PRIMARY KEY (`cryptomerchant_order_id`)
            ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
        ");

        $this->load->model('setting/setting');

        $defaults = array();

        $defaults['payment_cryptomerchant_pending_status_id'] = 1;
        $defaults['payment_cryptomerchant_paid_status_id'] = 2;
        $defaults['payment_cryptomerchant_invalid_status_id'] = 10;
        $defaults['payment_cryptomerchant_expired_status_id'] = 14;
        $defaults['payment_cryptomerchant_canceled_status_id'] = 7;
        $defaults['payment_cryptomerchant_refunded_status_id'] = 11;

        $this->model_setting_setting->editSetting('payment_cryptomerchant', $defaults);
    }

    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "cryptomerchant_order`;");
    }


}