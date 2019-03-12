<?php

require_once(DIR_SYSTEM . 'library/cryptomerchant/cryptomerchant-php/init.php');

class ControllerExtensionPaymentCryptoMerchant extends Controller
{
    private $error = array();

    public function index(){
        $this->load->language('extension/payment/cryptomerchant');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('localisation/order_status');
        $this->load->model('localisation/geo_zone');

        if($this->request->server['REQUEST_METHOD'] == "POST" && $this->validate()){
            $this->model_setting_setting->editSetting('payment_cryptomerchant', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        $data['action']             = $this->url->link('extension/payment/cryptomerchant', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel']             = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);
        $data['order_statuses']     = $this->model_localisation_order_status->getOrderStatuses();
        $data['geo_zones']          = $this->model_localisation_geo_zone->getGeoZones();
        $data['receive_currencies'] = array('BTC', 'EUR', 'USD', 'ETH', 'LTC', 'XMR');

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/cryptomerchant', 'user_token=' . $this->session->data['user_token'], true)
        );

        $fields = array(
            'payment_cryptomerchant_status', 
            'payment_cryptomerchant_api_auth_token',
            'payment_cryptomerchant_api_secret',
            'payment_cryptomerchant_receive_currency',
            'payment_cryptomerchant_pending_status_id',
            'payment_cryptomerchant_paid_status_id',
            'payment_cryptomerchant_invalid_status_id',
            'payment_cryptomerchant_expired_status_id',
            'payment_cryptomerchant_canceled_status_id',
            'payment_cryptomerchant_refunded_status_id',
            'payment_cryptomerchant_total',
            'payment_cryptomerchant_geo_zone_id',
        );


        foreach ($fields as $field) {
          if (isset($this->request->post[$field])) {
                $data[$field] = $this->request->post[$field];
            } else {
                $data[$field] = $this->config->get($field);
            }
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/cryptomerchant', $data));
    }

    public function validate(){
        if (!$this->user->hasPermission('modify', 'extension/payment/cryptomerchant')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!class_exists('CryptoMerchant\CryptoMerchant')) {
          $this->error['warning'] = $this->language->get('error_composer');
        }

        if (!$this->error) {
            $testConnection = \CryptoMerchant\CryptoMerchant::testConnection(array(
                'auth_token'    => $this->request->post['payment_cryptomerchant_api_auth_token'],
                ),
                ['enc_key'    => $this->request->post['payment_cryptomerchant_api_secret']]
            );

          if ($testConnection !== true) {
            $this->error['warning'] = $testConnection;
        }
    }

    return !$this->error;
    }

    public function install(){
        $this->load->model('extension/payment/cryptomerchant');
        $this->model_extension_payment_cryptomerchant->install();
    }
    
    public function uninstall(){
        $this->load->model('extension/payment/cryptomerchant');
        $this->model_extension_payment_cryptomerchant->uninstall();
    }
}
