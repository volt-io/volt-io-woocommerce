<?php

class WC_Gateway_Voltio extends WC_Payment_Gateway
{

	protected $voltio_initialized;
	public $order_hash;
	public $helper;
	public $mode;

	public function __construct()
	{
		$this->id = 'voltio';
		$this->icon = apply_filters('woocommerce_voltio_icon', '/wp-content/plugins/' . VOLTIO_PLUGIN_DIR . '/views/img/volt-logo.svg');
		$this->title = $this->get_option('title');
		$this->description = $this->get_option('description', ' ');
		$this->setup_properties();
		$this->init_form_fields();
		$this->init_settings();
		$this->order_hash = $_SESSION['order_hash'];

		$this->helper = new VoltioHelper();
		$this->mode = $this->helper->get_voltio_option(array('woocommerce_voltio_settings', 'mode'));
		// Saving hook
		add_action('woocommerce_update_options_payment_gateways_voltio', array($this, 'process_admin_options'));

		// Payment listener/API hook
		add_action('woocommerce_api_wc_gateway_voltio', array($this, 'gateway_ipn'));

		//ajax hooks

	}

	protected function setup_properties()
	{
		$this->method_title = __('Volt: Pay by Bank', 'voltio');
		$this->title = __('Pay by Bank', 'voltio');
		$this->method_description = __('Official Volt payment gateway for WooCommerce. If you do not already have Volt account, <a href="https://www.volt.io/contact/" target="_blank">please register</a>.<br />If you have any problems configuring plugin, please check our <a href="https://docs.volt.io/plugins/woocommerce/" target="_blank">documentation page</a>.', 'voltio');
		$this->has_fields = true;
		$this->supports = array('products', 'refunds');
	}

	public function init_form_fields()
	{
		$this->voltio_init_form_fields();
	}

	public function voltio_init_form_fields()
	{
		$this->form_fields = array_merge(
			$this->get_form_fields_basic(),
			$this->get_form_field_config(),
			array(
				'notifications_url' => array(
					'title' => __('Payment notifications URL', 'voltio') . ': ',
					'type' => 'title',
					'description' => add_query_arg('wc-api', 'WC_Gateway_Voltio', home_url('/')),
				),
				'return_urls' => array(
					'title' => __('Success, pending, failure, cancellation redirection URL', 'voltio') . ': ',
					'type' => 'title',
					'description' => home_url('/order-received/'),
				),
			)
		);
	}

	private function get_form_fields_basic()
	{
		return array(
			'enabled' => array(
				'title' => __('Enable/Disable', 'voltio'),
				'label' => __('Enable Volt: Pay by Bank', 'voltio'),
				'type' => 'checkbox',
				//                'description' => __('If you do not already have Volt account, <a href="https://www.volt.io/contact/" target="_blank">please register</a>.', 'voltio'),
				'default' => 'no',
			),
			//            'title' => [
			//                'title' => __('Title:', 'voltio'),
			//                'type' => 'text',
			//                'default' => __('Pay by Bank', 'voltio'),
			//                'value' => __('Pay by Bank', 'voltio'),
			//                'desc_tip' => false
			//            ],
			//            'description' => [
			//                'title' => __('Description:', 'voltio'),
			//                'type' => 'text',
			//                'description' => __('Description of Volt Payment Gateway that users sees on checkout page.', 'tpay'),
			//                'default' => __('Volt Payment Gateway description', 'voltio'),
			//                'desc_tip' => true
			//            ]
		);
	}


	public function process_refund($order_id, $amount = null, $reason = '')
	{
		$order = new WC_Order($order_id);
		$client = new VoltioClient();
		$token = $client->volt_refund();
		try {
			return true;
		} catch (Exception $exception) {
			error_log("Can't process refund");
			return false;
		}
	}

	public function gateway_ipn()
	{
		$input = file_get_contents('php://input');
		$body = json_decode($input);
		global $wpdb;
		$order_id = $wpdb->get_var($wpdb->prepare('select post_id from ' . $wpdb->postmeta . ' where meta_value = %s', $body->reference));
		update_post_meta($order_id, 'current_volt_status', $body->status);
		update_post_meta($order_id, 'volt_payment_id', $body->payment);
		$order = new WC_Order($order_id);
		$status = $body->status;
		$detailedStatus = false;
		if (isset($body->detailedStatus)) {
			$detailedStatus = $body->detailedStatus;
		}
		$hold_duration = get_option('woocommerce_hold_stock_minutes', '180');
		if ($hold_duration > 180) {
			$hold_duration = 180;
		}
		$hold_duration = $hold_duration * 60;
		if (!wp_next_scheduled('voltio_cancel_order', array($order_id))) {
			wp_schedule_single_event(current_time('timestamp') + $hold_duration, 'voltio_cancel_order', array($order_id));
		}
		switch ($status) {
			case 'RECEIVED':
				$order->update_status('processing');
				$order->payment_complete($order->get_transaction_id());
				$this->schedule_cancel_order($order_id);
				$this->helper->volt_logger('IPN log: The status from the gateway was received. Value: ' . $status . ' Order id: ' . $order_id);
				break;

			case 'NOT_RECEIVED':
				$order->update_status('cancelled');
				$this->helper->volt_logger('IPN log: The status from the gateway was received. Value: ' . $status . ' Order id: ' . $order_id);
				break;

			case 'FAILED':
				$this->helper->volt_logger('IPN log: The status from the gateway was received. Value: ' . $status . ', detailed status: ' . $detailedStatus);
				if ('ABANDONED_BY_USER' === $detailedStatus) {
					if (!wp_next_scheduled('voltio_cancel_order', array($order_id))) {
						wp_schedule_single_event(current_time('timestamp') + $hold_duration, 'voltio_cancel_order', array($order_id));
						$this->helper->volt_logger('IPN log: a task has been scheduled for an order with the status "abandoned by user". Order id: ' . $order_id);
					}
				} else {
					$order->update_status('cancelled');
					if (!wp_next_scheduled('voltio_cancel_order', array($order_id))) {
						wp_schedule_single_event(current_time('timestamp') + $hold_duration, 'voltio_cancel_order', array($order_id));
					}
				}
				break;
		}
		ob_flush();
		die();
	}

	public function schedule_cancel_order($order_id)
	{
		wp_schedule_single_event(180 * 60, 'cancel_order', array('order_id' => $order_id));
	}

	private function get_form_field_config()
	{
		$config = array();

		$fields = $this->voltio_fields();
		$settings = array();
		foreach ($fields as $field => $data) {
			$settings[$field] = array(
				'title' => $data['label'],
				'type' => isset($data['type']) ? $data['type'] : 'text',
				'description' => $data['description'],
				'class' => $data['class'],
				'desc_tip' => true,
			);
			if (isset($data['options'])) {
				$settings[$field]['options'] = $data['options'];
			}
		}
		$config += $settings;
		return $config;
	}

	public function voltio_fields()
	{
		return array(
			'mode' => array(
				'label' => __('Environment', 'voltio'),
				'description' => __('Mode description', 'voltio'),
				'type' => 'select',
				'class' => 'fields-toggler-by-mode',
				'options' => array(
					'sandbox' => __('Set Sandbox environment', 'voltio'),
					'production' => __('Set Production environment', 'voltio'),
				),
			),
			'client_id_sandbox' => array(
				'label' => __('Client ID', 'voltio'),
				'description' => __('Client ID description', 'voltio'),
				'class' => 'toggle-by-mode sandbox',
			),
			'client_secret_sandbox' => array(
				'label' => __('Client secret', 'voltio'),
				'description' => __('Client secret description', 'voltio'),
				'class' => 'toggle-by-mode sandbox',
			),
			'api_username_sandbox' => array(
				'label' => __('API username', 'voltio'),
				'description' => __('API username description', 'voltio'),
				'class' => 'toggle-by-mode sandbox',
			),
			'api_password_sandbox' => array(
				'label' => __('API password', 'voltio'),
				'description' => __('API password description', 'voltio'),
				'class' => 'toggle-by-mode sandbox',
			),
			'notification_secret_sandbox' => array(
				'label' => __('Notification secret', 'voltio'),
				'description' => __('Notification secret description', 'voltio'),
				'class' => 'toggle-by-mode sandbox',
			),
			'client_id_production' => array(
				'label' => __('Client ID', 'voltio'),
				'description' => __('Client ID description', 'voltio'),
				'class' => 'toggle-by-mode production',
			),
			'client_secret_production' => array(
				'label' => __('Client secret', 'voltio'),
				'description' => __('Client secret description', 'voltio'),
				'class' => 'toggle-by-mode production',
			),
			'api_username_production' => array(
				'label' => __('API username', 'voltio'),
				'description' => __('API username description', 'voltio'),
				'class' => 'toggle-by-mode production',
			),
			'api_password_production' => array(
				'label' => __('API password', 'voltio'),
				'description' => __('API password description', 'voltio'),
				'class' => 'toggle-by-mode production',
			),
			'notification_secret_production' => array(
				'label' => __('Notification secret', 'voltio'),
				'description' => __('Notification secret description', 'voltio'),
				'class' => 'toggle-by-mode production',
			),
		);
	}

	public function generate_appearance_html()
	{
		ob_start();
		require '../views/html/widget-settings.php';
		return ob_get_clean();
	}


	public function payment_gateway_is_enabled()
	{
		if ($this->helper->get_voltio_option(array('woocommerce_voltio_settings', 'enabled')) === 'yes') {
			return true;
		}
		return false;
	}

	public function payment_fields()
	{
		include plugin_dir_path(__FILE__) . '../views/html/gateway-content.php';
	}
}
