<?php
/*
Plugin Name: GiveWP – Razorpay Gateway
Description: Razorpay payment gateway for GiveWP (on-site checkout + webhooks).
Version: 1.0.0
Author: Mansour M
*/

if (!defined('ABSPATH')) exit;

define('FA_GIVERZP_DIR', plugin_dir_path(__FILE__));
define('FA_GIVERZP_URL', plugin_dir_url(__FILE__));

require __DIR__.'/vendor/autoload.php';

add_action('givewp_register_payment_gateway', function($registrar) {
    require_once __DIR__.'/src/Gateway/RazorpayGateway.php';
    $registrar->registerGateway(\FA\GiveRazorpay\Gateway\RazorpayGateway::class);
});

add_action('rest_api_init', function(){
    (new \FA\GiveRazorpay\Routes\CreateOrder())->register();
    (new \FA\GiveRazorpay\Routes\VerifyPayment())->register();
    (new \FA\GiveRazorpay\Routes\Webhook())->register();
});
