<?php
/**
 * Plugin Name: Volt: Pay by Bank
 * Plugin URI: https://volt.io
 * Description: Volt.io payment gateway for WooCommerce
 * Version: 1.1
 * Author: Volt.io
 * Author URI: http://volt.io
 * License: LGPL 3.0
 * Text Domain: voltio
 * Domain Path: /lang
 * WC requires at least: 5.5
 * WC tested up to: 5.5
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();
/*
 * Add new gateway
 */
define('VOLTIO_PLUGIN_VERSION', '1.1');
define('VOLTIO_PLUGIN_DIR', dirname(plugin_basename(__FILE__)));
add_action('plugins_loaded', 'init_gateway_voltio');
add_action( 'voltio_cancel_order', 'voltio_cancel_unpaid_order', 10, 1 );
add_filter('plugin_action_links', 'add_voltio_settings_link', 10, 2);
register_activation_hook(__FILE__, 'voltio_on_activate');

function voltio_on_activate()
{
    //on activate actions
}

function add_voltio_settings_link($links, $file) {
    if ($file === 'woocommerce-gateway-voltio/woocommerce-gateway-voltio.php') {
        $settings_link = array(
            'settings' => '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=voltio')) . '">' . __('Settings', 'voltio') . '</a>'
        );
        $links = array_merge($links, $settings_link);
    }
    return $links;
}

function voltio_cancel_unpaid_order($order_id){
    $order = new WC_Order($order_id);
    if($order->get_status() == 'pending'){
        $order->update_status('cancelled');
    }
}

function init_gateway_voltio()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    load_plugin_textdomain('voltio', false, VOLTIO_PLUGIN_DIR . '/lang/');
//    require_once('vendor/autoload.php');
    require_once('includes/VoltioHelper.php');
    require_once('includes/VoltioClient.php');
    require_once('includes/WC_Gateway_Voltio.php');
    add_filter('woocommerce_payment_gateways', 'add_voltio_gateways');
}

add_action('wp_ajax_init_voltio', 'init_voltioo');
add_action('wp_ajax_nopriv_init_voltio', 'init_voltioo');

function init_voltioo()
{
    echo 'qwqw';
    wp_die();
}

function add_voltio_gateways($gateways)
{
    $gateways[] = 'WC_Gateway_Voltio';
    return $gateways;
}

if (is_admin()) {
    add_action('admin_enqueue_scripts', 'enqueue_voltio_admin_assets');
} else {
    add_action('wp_enqueue_scripts', 'enqueue_voltio_gateway_assets');
}


//enqueue assets
function enqueue_voltio_admin_assets()
{
    wp_enqueue_script('voltio_admin_js', plugin_dir_url(__FILE__) . 'views/js/admin.js', ['wp-color-picker'], time());
    wp_enqueue_style('voltio_admin_css', plugin_dir_url(__FILE__) . 'views/css/admin.css', [], time());
    wp_enqueue_style('wp-color-picker');
}

function enqueue_voltio_gateway_assets()
{
    wp_enqueue_script('voltio_gateway_js', plugin_dir_url(__FILE__) . 'views/js/main.js', [], time(), true);
    wp_enqueue_script('voltio_gateway_sdk', 'https://js.volt.io/v1', [], null, false);
    wp_enqueue_style('voltio_gateway_css', plugin_dir_url(__FILE__) . 'views/css/main.css', [], time());
    wp_localize_script(
        'voltio_gateway_js',
        'voltio_obj',
        array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ajax-nonce')
        )
    );
}


add_action('wp_footer', 'volt_modal');

function volt_modal(){
    include('views/html/modal.php');
}

add_action('wp_footer', 'checkout_billing_email_js_ajax');
function checkout_billing_email_js_ajax()
{
    // Only on Checkout
    if (is_checkout() && !is_wc_endpoint_url()) :
        ?>
        <script type="text/javascript">
            jQuery(function ($) {

            });
        </script>
    <?php
    endif;
}

add_action('wp_ajax_ajax_order', 'submited_ajax_order_data');
add_action('wp_ajax_nopriv_ajax_order', 'submited_ajax_order_data');
function submited_ajax_order_data()
{
    if (isset($_POST['fields']) && !empty($_POST['fields'])) {

        $order = new WC_Order();
        $cart = WC()->cart;
        $checkout = WC()->checkout;
        $data = [];
        $valid = true;
        $used_labels = [];
        $order_hash = $_REQUEST['order_hash'];

        // Loop through posted data array transmitted via jQuery
        foreach ($_POST['fields'] as $values) {
            // Set each key / value pairs in an array
            $data[$values['name']] = $values['value'];
        }
        foreach (WC()->checkout()->checkout_fields as $type => $fields) {
            foreach ($fields as $key => $value) {
                if (!empty($value['required']) && empty($data[$key])) {
                    if (!in_array($value['label'], $used_labels)) {
                        array_push($used_labels, $value['label']);
                        wc_add_notice(sprintf(__('%s is a required field.', 'woocommerce'), $value['label']), 'error', array('id' => $key));
                    }
                    $valid = false;
                }
            }
        }
        if (!$_POST['voltio-selected-bank'] && $_POST['payment_method'] == 'voltio') {
            wc_add_notice(__('Select bank', 'voltio'), 'error');
            $valid = false;
        }
        if (!$valid) {
            return false;
        }


        $cart_hash = md5(json_encode(wc_clean($cart->get_cart_for_session())) . $cart->total);
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();

        // Loop through the data array
        foreach ($data as $key => $value) {
            // Use WC_Order setter methods if they exist
            if (is_callable(array($order, "set_{$key}"))) {
                $order->{"set_{$key}"}($value);

                // Store custom fields prefixed with wither shipping_ or billing_
            } elseif ((0 === stripos($key, 'billing_') || 0 === stripos($key, 'shipping_'))
                && !in_array($key, array('shipping_method', 'shipping_total', 'shipping_tax'))) {
                $order->update_meta_data('_' . $key, $value);
            }
        }

        $order->set_created_via('checkout');
        $order->set_cart_hash($cart_hash);
        $order->set_customer_id(apply_filters('woocommerce_checkout_customer_id', get_current_user_id() ? get_current_user_id() : ''));
        $order->set_currency(get_woocommerce_currency());
        $order->set_prices_include_tax('yes' === get_option('woocommerce_prices_include_tax'));
        $order->set_customer_ip_address(WC_Geolocation::get_ip_address());
        $order->set_customer_user_agent(wc_get_user_agent());
        $order->set_customer_note(isset($data['order_comments']) ? $data['order_comments'] : '');
        $order->set_payment_method(isset($available_gateways[$data['payment_method']]) ? $available_gateways[$data['payment_method']] : $data['payment_method']);
        $order->set_shipping_total($cart->get_shipping_total());
        $order->set_discount_total($cart->get_discount_total());
        $order->set_discount_tax($cart->get_discount_tax());
        $order->set_cart_tax($cart->get_cart_contents_tax() + $cart->get_fee_tax());
        $order->set_shipping_tax($cart->get_shipping_tax());
        $order->set_total($cart->get_total('edit'));

        $checkout->create_order_line_items($order, $cart);
        $checkout->create_order_fee_lines($order, $cart);
        $checkout->create_order_shipping_lines($order, WC()->session->get('chosen_shipping_methods'), WC()->shipping->get_packages());
        $checkout->create_order_tax_lines($order, $cart);
        $checkout->create_order_coupon_lines($order, $cart);

        /**
         * Action hook to adjust order before save.
         * @since 3.0.0
         */
        do_action('woocommerce_checkout_create_order', $order, $data);

        // Save the order.
        $order_id = $order->save();

        do_action('woocommerce_checkout_update_order_meta', $order_id, $data);
        update_post_meta($order_id, 'order_hash', $order_hash);
        WC()->cart->empty_cart();
        echo $order_id;
    }
    die();
}

add_action('woocommerce_thankyou', 'custom_content_thankyou', 10, 1);
function custom_content_thankyou($order_id)
{
    if (get_post_meta($order_id, 'order_hash')) {
        $current_volt_status = get_post_meta($order_id, 'current_volt_status', true);
        echo '<p> ' .__('Volt status: ', 'volt'). $current_volt_status . '</p>';
        switch($current_volt_status){
            case 'COMPLETED';

            break;
        }
    }
}

add_action('init', function () {
    if ($_REQUEST['volt']) {
        $volt = base64_decode($_REQUEST['volt']);
        $volt = json_decode($volt, true);
        $res = get_order_data_by_hash($volt['uniqueReference']);
        $return_wc_endpoint = wc_get_endpoint_url( 'order-received');
        $return_url = wc_get_page_permalink( 'checkout' ) . ltrim($return_wc_endpoint, '/');
        update_post_meta($res['order_id'], 'current_volt_status', $volt['status']);
        wp_redirect($return_url . '/' . $res['order_id'] . '/?key=' . $res['order_key']);
        exit();
    }
});

function get_order_data_by_hash($hash)
{
    global $wpdb;
    $order = $wpdb->get_var('select post_id from ' . $wpdb->postmeta . ' where meta_value = "' . $hash . '"');
    $order_key = get_post_meta($order, '_order_key', true);
    $result = [
        'order_id' => $order,
        'order_key' => $order_key
    ];
    return $result;
}

add_action('woocommerce_after_checkout_validation', 'rei_after_checkout_validation');

function rei_after_checkout_validation($posted)
{

    if (!$_POST['voltio-selected-bank'] && $_POST['payment_method'] == 'voltio') {
        wc_add_notice(__('Select bank', 'voltio'), 'error');
        return false;
    }

}

function cancel_order($args){
    update_option('jobtest' . time(), $args['order_id']);
}

