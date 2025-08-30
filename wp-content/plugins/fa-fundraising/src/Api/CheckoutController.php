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

            register_rest_route('faf/v1','/checkout/verify',[
                'methods'=>'POST',
                'callback'=>[$this,'verify'],
                'permission_callback'=>'__return_true'
            ]);
        });
    }

    public function verify(\WP_REST_Request $req) {
        $p = $req->get_json_params() ?: [];
        $order_id = sanitize_text_field($p['order_id'] ?? '');
        $payment_id = sanitize_text_field($p['payment_id'] ?? '');
        $signature  = sanitize_text_field($p['signature'] ?? '');
        $notes      = is_array($p['notes'] ?? null) ? $p['notes'] : [];

        if (!$order_id || !$payment_id || !$signature) {
            return new \WP_Error('bad','order_id, payment_id, signature required',['status'=>400]);
        }

        // Verify signature
        $api = new \Razorpay\Api\Api(get_option('fa_rzp_key_id',''), get_option('fa_rzp_key_secret',''));
        try {
            $api->utility->verifyPaymentSignature([
                'razorpay_order_id'   => $order_id,
                'razorpay_payment_id' => $payment_id,
                'razorpay_signature'  => $signature,
            ]);
        } catch (\Throwable $e) {
            return new \WP_Error('sig','Bad signature',['status'=>401]);
        }

        // Pull payment from Razorpay to get amount/status/notes
        $pay = $api->payment->fetch($payment_id)->toArray();
        if (($pay['status'] ?? '') !== 'captured') {
            return ['ok'=>false,'status'=>$pay['status'] ?? 'unknown'];
        }

        $email = sanitize_email($pay['email'] ?? ($pay['contact'] ?? ''));
        $n = is_array($pay['notes'] ?? null) ? $pay['notes'] : $notes;
        $user_id = !empty($n['user_id']) ? (int)$n['user_id'] : (get_user_by('email',$email)->ID ?? null);

        // Insert if webhook hasn’t already
        global $wpdb;
        $don = $wpdb->prefix.'fa_donations';
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $don WHERE razorpay_payment_id=%s", $payment_id));
        if (!$exists) {
            $amount = ((int)$pay['amount'])/100;
            $currency = $pay['currency'] ?? 'INR';

            $type = sanitize_text_field($n['type'] ?? 'general');
            $orphan_id = isset($n['orphan_id']) ? (int)$n['orphan_id'] : null;
            $cause_id  = isset($n['cause_id']) ? (int)$n['cause_id']  : null;
            $fy = \FA\Fundraising\Payments\RazorpayService::fy_from_date(gmdate('Y-m-d'));

            // simple sequence
            $opt = 'fa_receipt_seq_'.$fy; $seq=(int)get_option($opt,0)+1; update_option($opt,$seq,false);
            $receipt_no = sprintf('%s/%06d', $fy, $seq);

            $wpdb->insert($don, [
                'created_at'=> current_time('mysql', true),
                'user_id'   => $user_id ?: null,
                'donor_name'=> sanitize_text_field($n['donor_name'] ?? ''),
                'donor_email'=> $email,
                'amount'    => $amount,
                'currency'  => $currency,
                'type'      => $type,
                'orphan_id' => $orphan_id ?: null,
                'cause_id'  => $cause_id  ?: null,
                'razorpay_payment_id'=> $payment_id,
                'razorpay_order_id'  => $order_id,
                'status'    => 'captured',
                'financial_year'=> $fy,
                'receipt_no'=> $receipt_no,
                'meta'      => maybe_serialize(['notes'=>$n,'verified_return'=>true])
            ]);
        }

        if ($user_id) {
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
        }

        return ['ok'=>true, 'logged_in'=> (bool) $user_id];
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
