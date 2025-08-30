<?php
/**
 * Plugin Name: GiveWP Razorpay Gateway
 * Description: Standalone Razorpay payment gateway for GiveWP donations.
 * Version: 1.0.0
 * Author: Mansour M
 * License: GPL-2.0-or-later
 * Text Domain: givewp-razorpay
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register the Razorpay gateway with GiveWP.
add_action( 'givewp_register_payment_gateway', static function ( $paymentGatewayRegister ) {
    require_once __DIR__ . '/class-razorpay-gateway.php';

    $paymentGatewayRegister->registerGateway( RazorpayGateway::class );
} );

