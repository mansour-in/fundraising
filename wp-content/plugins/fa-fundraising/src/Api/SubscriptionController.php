<?php
namespace FA\Fundraising\Api;

use FA\Fundraising\Payments\RazorpayService;

if (!defined('ABSPATH')) exit;

class SubscriptionController {
    public function init(): void {
        add_action('rest_api_init', function(){
            register_rest_route('faf/v1','/subscriptions/create',[
                'methods'=>'POST',
                'callback'=>[$this,'create'],
                'permission_callback'=>'__return_true'
            ]);
        });
    }

    public function create(\WP_REST_Request $req) {
        $p = $req->get_json_params() ?: [];
        $amount = (float)($p['amount'] ?? 0);  // in INR
        $currency = $p['currency'] ?? 'INR';
        $email = sanitize_email($p['email'] ?? '');
        $name  = sanitize_text_field($p['name'] ?? '');
        $phone = preg_replace('/[^0-9+]/','', (string)($p['phone'] ?? ''));
        $orphan_id = (int)($p['orphan_id'] ?? 0);

        if ($amount <= 0 || !$email || !$orphan_id) {
            return new \WP_Error('bad','amount, email, orphan_id required',['status'=>400]);
        }

        $user_id = self::get_or_create_donor_user($email, $name);
        $api = new \Razorpay\Api\Api(get_option('fa_rzp_key_id',''), get_option('fa_rzp_key_secret',''));

        // 1) Find or create a monthly plan for this amount
        $key = 'fa_rzp_plan_monthly_' . $currency . '_' . (int)round($amount*100);
        $plan_id = get_option($key, '');
        try {
            if ($plan_id) { $api->plan->fetch($plan_id); } // validates
        } catch (\Throwable $e) { $plan_id = ''; }

        if (!$plan_id) {
            $plan = $api->plan->create([
                'period'   => 'monthly',
                'interval' => 1,
                'item' => [
                    'name'     => 'Monthly Sponsorship ₹'.(int)$amount,
                    'amount'   => (int) round($amount*100),
                    'currency' => $currency
                ]
            ]);
            $plan_id = $plan['id'];
            update_option($key, $plan_id, false);
        }

        // 2) Create subscription with that plan
        $sub = $api->subscription->create([
            'plan_id' => $plan_id,
            'total_count' => 0, // until cancelled
            'customer_notify' => 1,
            'notes' => [
                'orphan_id'   => (string)$orphan_id,
                'user_id'     => (string)$user_id,
                'donor_email' => $email,
                'donor_name'  => $name
            ]
        ]);

        $rzp = new RazorpayService();
        return ['ok'=>true, 'key_id'=>$rzp->keyId(), 'subscription'=>$sub->toArray()];
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
