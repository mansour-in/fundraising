<?php
/**
 * Plugin Name: GiveWP Razorpay Gateway
 * Description: Adds Razorpay as a payment option for GiveWP donations.
 * Version: 1.0.0
 * Author: Mansour M
 * License: GPL-2.0-or-later
 * Text Domain: givewp-razorpay
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Register Razorpay gateway with GiveWP.
 *
 * @param array $gateways Existing gateways.
 *
 * @return array Modified gateways.
 */
function givewp_razorpay_register_gateway( $gateways ) {
    $gateways['razorpay'] = [
        'admin_label'    => __( 'Razorpay', 'givewp-razorpay' ),
        'checkout_label' => __( 'Razorpay', 'givewp-razorpay' ),
    ];

    return $gateways;
}
add_filter( 'give_payment_gateways', 'givewp_razorpay_register_gateway' );

/**
 * Process a Razorpay donation.
 *
 * This is a stub that immediately marks the donation as complete. It should be
 * replaced with real Razorpay integration in future steps.
 *
 * @param array $purchase_data Donation data.
 */
function givewp_razorpay_process_donation( $purchase_data ) {
    $payment_id = give_insert_payment( $purchase_data );

    if ( $payment_id ) {
        give_update_payment_status( $payment_id, 'publish' );
    }

    return;
}
add_action( 'give_gateway_razorpay', 'givewp_razorpay_process_donation' );
