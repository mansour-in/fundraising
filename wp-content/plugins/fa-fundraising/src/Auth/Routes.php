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

            register_rest_route('faf/v1', '/me', [
                'methods'  => 'POST',
                'callback' => [$this, 'update_me'],
                'permission_callback' => function() {
                    return is_user_logged_in();
                }
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

    public function update_me(\WP_REST_Request $req) {
        if (!is_user_logged_in()) {
            return new \WP_Error('auth','Not logged in',['status'=>401]);
        }
        // Verify REST nonce (expects X-WP-Nonce)
        if (!wp_verify_nonce($req->get_header('X-WP-Nonce'), 'wp_rest')) {
            return new \WP_Error('bad_nonce','Invalid nonce',['status'=>403]);
        }

        $u = wp_get_current_user();
        $params = $req->get_json_params() ?: [];

        // Sanitize fields
        $name   = isset($params['name']) ? sanitize_text_field($params['name']) : '';
        $phone  = isset($params['phone']) ? preg_replace('/[^0-9+]/','', (string)$params['phone']) : '';
        $pan    = isset($params['pan']) ? strtoupper(preg_replace('/[^A-Z0-9]/i','', (string)$params['pan'])) : '';
        $addr1  = isset($params['address_line1']) ? sanitize_text_field($params['address_line1']) : '';
        $addr2  = isset($params['address_line2']) ? sanitize_text_field($params['address_line2']) : '';
        $city   = isset($params['city']) ? sanitize_text_field($params['city']) : '';
        $state  = isset($params['state']) ? sanitize_text_field($params['state']) : '';
        $pin    = isset($params['pin']) ? preg_replace('/[^0-9]/','', (string)$params['pin']) : '';
        $country= isset($params['country']) ? sanitize_text_field($params['country']) : '';

        if ($name) {
            wp_update_user(['ID'=>$u->ID, 'display_name'=>$name]);
        }
        if ($phone)  update_user_meta($u->ID, 'fa_phone', $phone);
        if ($pan)    update_user_meta($u->ID, 'fa_pan', $pan);
        if ($addr1 !== '') update_user_meta($u->ID, 'fa_address_line1', $addr1);
        if ($addr2 !== '') update_user_meta($u->ID, 'fa_address_line2', $addr2);
        if ($city  !== '') update_user_meta($u->ID, 'fa_city', $city);
        if ($state !== '') update_user_meta($u->ID, 'fa_state', $state);
        if ($pin   !== '') update_user_meta($u->ID, 'fa_pin', $pin);
        if ($country !== '') update_user_meta($u->ID, 'fa_country', $country);

        return [
            'ok'=>true,
            'user'=>[
                'id'=>$u->ID,
                'email'=>$u->user_email,
                'name'=>get_user_by('id',$u->ID)->display_name,
                'meta'=>[
                    'phone'=>get_user_meta($u->ID,'fa_phone',true),
                    'pan'=>get_user_meta($u->ID,'fa_pan',true),
                    'address_line1'=>get_user_meta($u->ID,'fa_address_line1',true),
                    'address_line2'=>get_user_meta($u->ID,'fa_address_line2',true),
                    'city'=>get_user_meta($u->ID,'fa_city',true),
                    'state'=>get_user_meta($u->ID,'fa_state',true),
                    'pin'=>get_user_meta($u->ID,'fa_pin',true),
                    'country'=>get_user_meta($u->ID,'fa_country',true),
                ]
            ]
        ];
    }
}
