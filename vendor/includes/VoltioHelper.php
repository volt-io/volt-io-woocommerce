<?php

class VoltioHelper
{
    public function dev_option($name, $value)
    {
        update_option($name . ' ' . time() . ' ' . date('Y-m-d H:i:s'), print_r($value, true));
    }

    public function get_voltio_option($key)
    {
        if (!is_array($key)) {
            return false;
        }
        if (@get_option($key[0])[$key[1]]) {
            return wp_specialchars_decode(get_option($key[0])[$key[1]]);
        }
        return false;
    }

    public function volt_logger($log){
        $context = array( 'source' => 'volt' );
        $logger = wc_get_logger();
        $logger->debug( $log . "\r\n-----------\r\n", $context );
    }

    public function generate_random_string($length = 18)
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}