<?php

class VoltioClient {

	protected $mode;
	public $production_url = 'https://api.volt.io';
	public $sandbox_url    = 'https://api.sandbox.volt.io';
	public $api_url;
	public $access_token;
	public $idpay;
	public $bearer;
	public $helper;
	public $cart_contents_total;
	public $order_hash;

	public function __construct() {
		$this->helper           = new VoltioHelper();
		$order_hash             = $this->helper->generate_random_string( 15 );
		$this->order_hash       = $order_hash;
		$_SESSION['order_hash'] = $this->order_hash;
		if ( isset( WC()->cart->cart_contents_total ) && WC()->cart->cart_contents_total > 0 ) {
			$this->cart_contents_total = WC()->cart->cart_contents_total * 100;
		} else {
			$this->cart_contents_total = 5500;
		}
		$this->mode = $this->helper->get_voltio_option( array( 'woocommerce_voltio_settings', 'mode' ) );
		if ( 'sandbox' === $this->mode ) {
			$this->api_url = $this->sandbox_url;
		} else {
			$this->api_url = $this->production_url;
		}
		add_action( 'wp_ajax_fetch_token', array( $this, 'fetch_token' ) );
		add_action( 'wp_ajax_nopriv_fetch_token', array( $this, 'fetch_token' ) );
		add_action( 'wp_ajax_get_dropin_payments', array( $this, 'get_dropin_payments' ) );
		add_action( 'wp_ajax_nopriv_get_dropin_payments', array( $this, 'get_dropin_payments' ) );
	}

	public function volt_refund() {
		$token = $this->fetch_token( true );
		return $token;
	}

	public function fetch_token( $return = false ) {
		if ( isset( $_REQUEST['nonce'] ) ) {
			$nonce = sanitize_text_field( $_REQUEST['nonce'] );
			if ( wp_verify_nonce( $nonce, 'ajax-nonce' ) ) {
				$client_id     = $this->helper->get_voltio_option( array( 'woocommerce_voltio_settings', 'client_id_' . $this->mode ) );
				$client_secret = $this->helper->get_voltio_option( array( 'woocommerce_voltio_settings', 'client_secret_' . $this->mode ) );
				$username      = $this->helper->get_voltio_option( array( 'woocommerce_voltio_settings', 'api_username_' . $this->mode ) );
				$password      = $this->helper->get_voltio_option( array( 'woocommerce_voltio_settings', 'api_password_' . $this->mode ) );
				$grant_type    = 'password';
				$data          = array(
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'username'      => $username,
					'password'      => $password,
					'grant_type'    => $grant_type,
				);
				$header        = array(
					'Content-Type: application/x-www-form-urlencoded',
				);
				$curl          = curl_init();
				curl_setopt( $curl, CURLOPT_URL, $this->api_url . '/oauth' );
				curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
				curl_setopt( $curl, CURLOPT_HEADER, false );
				curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, 'POST' );
				curl_setopt( $curl, CURLINFO_HEADER_OUT, true );
				curl_setopt( $curl, CURLOPT_HTTPHEADER, $header );
				curl_setopt( $curl, CURLOPT_POSTFIELDS, http_build_query( $data ) );
				curl_setopt( $curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
				curl_setopt( $curl, CURLOPT_POST, true );
				curl_setopt( $curl, CURLOPT_FAILONERROR, true );
				$response = curl_exec( $curl );
				$error    = curl_error( $curl );
				curl_close( $curl );
				$resp = null;
				if ( $error ) {
					$this->helper->volt_logger( 'Can\'t fetch access token' );
					wc_add_notice( __( 'Error: ', 'voltio' ) . $error, 'error' );
					return false;
				} else {
					$response = json_decode( $response, true );
					$this->helper->volt_logger( 'Successfully received the token' );
					if ( array_key_exists( 'access_token', $response ) ) {
						$resp = $response['access_token'];
					}
				}
				if ( $return ) {
					return esc_html( $resp );
				} else {
					$_SESSION['access_token'] = $resp;
					wp_die();
				}
			} else {
				echo 'badnonce';
			}
		} else {
			echo 'nononce';
		}
	}

	public function get_dropin_payments() {
		$this->access_token = isset( $_SESSION['access_token'] ) ? sanitize_text_field( $_SESSION['access_token'] ) : '';
		$currency           = get_woocommerce_currency();
		//        $currency = 'GBP';
		$header = array(
			'Authorization: Bearer ' . $this->access_token,
			'Content-Type: application/json',
			'Volt-Partner-Attribution-Id: cb23f073-7c16-4258-8fc1-83da2673e39b',
		);
		if ( get_current_user_id() ) {
			$payer_ref = 'userid' . get_current_user_id();
		} else {
			$payer_ref = 'notloggedin';
		}
		$return_wc_endpoint = wc_get_endpoint_url( 'order-received' );
		$return_url         = wc_get_page_permalink( 'checkout' ) . ltrim( $return_wc_endpoint, '/' );
		$first_name         = isset( $_REQUEST['first_name'] ) ? sanitize_text_field( $_REQUEST['first_name'] ) : 'Anonymous';
		$last_name          = isset( $_REQUEST['last_name'] ) ? sanitize_text_field( $_REQUEST['last_name'] ) : 'Anonymous';
		$total = floatval( WC()->cart->get_total('') );
		$total_float = wc_format_decimal($total);
		$data = array(
			'currencyCode'      => $currency,
			'amount'            => $total_float * 100,
			'type'              => 'OTHER',
			'uniqueReference'   => $this->order_hash,
			'payer'             => array(
				'reference' => $payer_ref,
				'firstName' => $last_name,
				'lastName'  => $first_name,
			),
			'notificationUrl'   => add_query_arg( 'wc-api', 'WC_Gateway_Voltio', home_url( '/' ) ),
			'paymentPendingUrl' => $return_url,
			'paymentCancelUrl'  => $return_url,
			'paymentSuccessUrl' => $return_url,
			'paymentFailureUrl' => $return_url,
		);
		$curl = curl_init();
		curl_setopt( $curl, CURLOPT_URL, $this->api_url . '/dropin' );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_HEADER, false );
		curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, 'POST' );
		curl_setopt( $curl, CURLINFO_HEADER_OUT, true );
		curl_setopt( $curl, CURLOPT_HTTPHEADER, $header );
		curl_setopt( $curl, CURLOPT_POSTFIELDS, json_encode( $data ) );
		curl_setopt( $curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
		curl_setopt( $curl, CURLOPT_POST, true );
		curl_setopt( $curl, CURLOPT_FAILONERROR, true );
		$response = curl_exec( $curl );
		$error = curl_error( $curl );
		curl_close( $curl );
		if ( $error ) {
			$this->helper->volt_logger( 'Dropn payments failure. CURL errno: ' . curl_errno( $curl ) . ', error: ' . $error . print_r($data, 1) );
			echo 'Error: ' . esc_html( $error ) . ', ' . esc_html( curl_errno( $curl ) );
		} else {
			$this->helper->volt_logger( print_r($data, 1) );
			$response = json_decode( $response, true );

			if ( array_key_exists( 'id', $response ) ) {
				echo json_encode(
					array(
						'payment_id' => $response['id'],
						'order_hash' => $this->order_hash,
					)
				);
			}
		}
		wp_die();
	}
}

new VoltioClient();
