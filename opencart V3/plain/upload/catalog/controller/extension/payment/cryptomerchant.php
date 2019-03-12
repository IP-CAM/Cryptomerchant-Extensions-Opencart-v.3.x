<?php

class ControllerExtensionPaymentCryptoMerchant extends Controller
{
	public function index(){
		$this->load->language('extension/payment/cryptomerchant');
        $this->load->model('checkout/order');

        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['action'] = $this->url->link('extension/payment/cryptomerchant/checkout', '', true);

        return $this->load->view('extension/payment/cryptomerchant', $data);
	}

	public function checkout()
    {
        $this->setupcryptomerchantClient();
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/cryptomerchant');

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $token = md5(uniqid(rand(), true));
        $description = [];

        foreach ($this->cart->getProducts() as $product) {
            $description[] = $product['quantity'] . ' × ' . $product['name'];
        }

        $amount = number_format($order_info['total'] * $this->currency->getvalue($order_info['currency_code']), 8, '.', '');
    
        $cm_order = \CryptoMerchant\Merchant\Order::create(array(
            'OrderId' => $order_info['order_id'],
            'amount' => $order_info['currency_code'] == 'USD' ? $amount : number_format($this->currency->convert($amount, $order_info['currency_code'],'USD'), 8, '.', ''),
            'currency' => 'USD',
            'receive_currency' => $this->config->get('payment_cryptomerchant_receive_currency'),
            'cancel_url' => html_entity_decode($this->url->link('extension/payment/cryptomerchant/cancel', '', true)),
            'callback_url' => html_entity_decode($this->url->link('extension/payment/cryptomerchant/callback', array('cm_token' => $token), true)),
            'success_url' => html_entity_decode($this->url->link('extension/payment/cryptomerchant/success', array('cm_token' => $token), true)),
            'title' => $this->config->get('config_meta_title') . ' Order #' . $order_info['order_id'],
            'description' => join($description, ', '),
            'token' => $token
        ));

        if ($cm_order) {
            $this->model_extension_payment_cryptomerchant->addOrder(array(
                'order_id' => $order_info['order_id'],
                'token' => $token,
                'cm_invoice_id' => $cm_order->response['id']
            ));

            $this->model_checkout_order->addOrderHistory($order_info['order_id'], $this->config->get('payment_cryptomerchant_order_status_id'));

            $this->response->redirect($cm_order->response['payment_url']);
        } else {
            $this->log->write("Order #" . $order_info['order_id'] . " is not valid. Please check cryptomerchant API request logs.");
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }
    }

    public function cancel()
    {
        $this->response->redirect($this->url->link('checkout/cart', ''));
    }

    public function success()
    {
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/cryptomerchant');

        $order = $this->model_extension_payment_cryptomerchant->getOrder($this->session->data['order_id']);
        
        if (empty($order) || strcmp($order['token'], $this->request->get['cm_token']) !== 0) {
            $this->response->redirect($this->url->link('common/home', '', true));
        } else {
            $this->response->redirect($this->url->link('checkout/success', '', true));
        }
    }

    public function callback()
    {
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/cryptomerchant');
        $post_data = \CryptoMerchant\CryptoMerchant::decrypt($this->config->get('payment_cryptomerchant_api_secret'),$this->request->post['encData']);
        $post_data = json_decode($post_data,true);

        $order_id = $post_data['OrderId'];
        $order_info = $this->model_checkout_order->getOrder($order_id);
        $ext_order = $this->model_extension_payment_cryptomerchant->getOrder($order_id);

        if (!empty($order_info) && !empty($ext_order) && strcmp($ext_order['token'], $post_data['token']) === 0) {
            $this->setupcryptomerchantClient();

            $cm_order = \CryptoMerchant\Merchant\Order::find($ext_order['cm_invoice_id']);
            if ($cm_order) {
                switch ($cm_order->status) {
                    case 'Completed':
                        $cm_order_status = 'payment_cryptomerchant_paid_status_id';
                        break;
                    case 'Invalid':
                        $cm_order_status = 'payment_cryptomerchant_invalid_status_id';
                        break;
                    case 'Expired':
                        $cm_order_status = 'payment_cryptomerchant_expired_status_id';
                        break;
                    case 'Canceled':
                        $cm_order_status = 'payment_cryptomerchant_canceled_status_id';
                        break;
                    case 'Refunded':
                        $cm_order_status = 'payment_cryptomerchant_refunded_status_id';
                        break;
                    default:
                        $cm_order_status = NULL;
                }

                if (!is_null($cm_order_status)) {
                    $this->model_checkout_order->addOrderHistory($order_id, $this->config->get($cm_order_status));
                }
            }
        }

        $this->response->addHeader('HTTP/1.1 200 OK');
    }

    private function setupcryptomerchantClient()
    {
        \CryptoMerchant\CryptoMerchant::config(array(
            'auth_token' => empty($this->config->get('payment_cryptomerchant_api_auth_token')) ? $this->config->get('payment_cryptomerchant_api_secret') : $this->config->get('payment_cryptomerchant_api_auth_token'),
            'user_agent' => 'cryptomerchant - OpenCart v' . VERSION . ' Extension v' . CryptoMerchant\CryptoMerchant::VERSION
        ));
    }
}