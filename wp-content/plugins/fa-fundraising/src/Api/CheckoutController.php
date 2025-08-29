<?php
namespace FA\Fundraising\Api;

use FA\Fundraising\Payments\RazorpayService;

if (!defined('ABSPATH')) exit;

class CheckoutController {
    public function init(): void {
        add_action('rest_api_init', function(){
            register_rest_route('faf/v1','/checkout/order',[
                'methods'=>'POST',
                'callback'=>[$this,'create_order'],
                'permission_callback'=>'__return_true'
            ]);
        });
    }

    public function create_order(\WP_REST_Request $req) {
        $p = $req->get_json_params() ?: [];
        $amount   = isset($p['amount']) ? (float)$p['amount'] : 0.0;
        $currency = $p['currency'] ?? 'INR';
        $type     = sanitize_text_field($p['type'] ?? 'general'); // general|cause|sponsorship
        $orphan_id= isset($p['orphan_id']) ? (int)$p['orphan_id'] : null;
        $cause_id = isset($p['cause_id']) ? (int)$p['cause_id'] : null;

        $email = sanitize_email($p['email'] ?? '');
        $name  = sanitize_text_field($p['name'] ?? '');
        $phone = preg_replace('/[^0-9+]/','', (string)($p['phone'] ?? ''));

        if ($amount <= 0 || !$email) {
            return new \WP_Error('bad','Amount and email required',['status'=>400]);
        }

        // ensure a donor user exists
        $user_id = self::get_or_create_donor_user($email, $name);

        $rzp = new RazorpayService();
        $notes = [
            'type' => $type,
            'orphan_id' => $orphan_id ?: '',
            'cause_id' => $cause_id ?: '',
            'donor_email' => $email,
            'donor_name'  => $name,
            'user_id' => (string)$user_id,
            'phone'   => $phone
        ];
        $order = $rzp->create_order($amount, $currency, $notes);
        return [
            'ok'=>true,
            'key_id'=>$rzp->keyId(),
            'order'=>$order
        ];
    }

    private static function get_or_create_donor_user(string $email, string $name=''): int {
        $u = get_user_by('email', $email);
        if ($u) return (int)$u->ID;
        $username = sanitize_user(current(explode('@',$email)).'_'.wp_generate_password(6,false,false), true);
        $pass = wp_generate_password(20, true, true);
        $uid  = wp_create_user($username, $pass, $email);
        if (is_wp_error($uid)) return 0;
        $wu = new \WP_User($uid);
        $wu->set_role('fa_donor');
        if ($name) wp_update_user(['ID'=>$uid,'display_name'=>$name]);
        return (int)$uid;
    }
}
