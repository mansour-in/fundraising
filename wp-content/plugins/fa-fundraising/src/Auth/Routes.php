<?php
namespace FA\Fundraising\Auth;

if (!defined('ABSPATH')) exit;

class Routes {
    public function init(): void {
        add_action('rest_api_init', function () {
            register_rest_route('faf/v1', '/auth/request-link', [
                'methods'  => 'POST',
                'callback' => [MagicLink::class, 'request_link'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('faf/v1', '/auth/request-otp', [
                'methods'  => 'POST',
                'callback' => [Otp::class, 'request_otp'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('faf/v1', '/auth/verify-otp', [
                'methods'  => 'POST',
                'callback' => [Otp::class, 'verify_otp'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('faf/v1', '/auth/logout', [
                'methods'  => 'POST',
                'callback' => [$this, 'logout'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('faf/v1', '/me', [
                'methods'  => 'GET',
                'callback' => [$this, 'me'],
                'permission_callback' => function() { return is_user_logged_in(); }
            ]);
        });
    }

    public function logout(\WP_REST_Request $req) {
        wp_logout();
        return ['ok' => true];
    }

    public function me(\WP_REST_Request $req) {
        $u = wp_get_current_user();
        return [
            'ok' => true,
            'user' => [
                'id' => $u->ID,
                'email' => $u->user_email,
                'name' => $u->display_name,
                'roles' => $u->roles,
            ],
        ];
    }
}
