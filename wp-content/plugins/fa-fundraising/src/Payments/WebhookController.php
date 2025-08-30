<?php
namespace FA\Fundraising\Payments;

use WP_Error;
use FA\Fundraising\Service\DonationEffects;
use FA\Fundraising\Service\EmailService;

if (!defined('ABSPATH')) exit;

class WebhookController {
    public function init(): void {
        add_action('rest_api_init', function(){
            register_rest_route('faf/v1','/webhook/razorpay',[
                'methods'=>'POST',
                'callback'=>[$this,'handle'],
                'permission_callback'=>'__return_true'
            ]);
        });
    }

    public function handle(\WP_REST_Request $req) {
        $payload = $req->get_body();
        $sig = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
        try {
            (new RazorpayService())->verify_webhook($payload, $sig);
        } catch (\Throwable $e) {
            return new WP_Error('sig','Invalid signature',['status'=>401]);
        }

        $event = json_decode($payload, true);
        if (!$event || empty($event['event']) || empty($event['payload'])) return ['ok'=>true];

        $event_id = $event['event'] . ':' . ($event['payload']['payment']['entity']['id'] ?? $event['payload']['order']['entity']['id'] ?? $event['payload']['subscription']['entity']['id'] ?? wp_generate_uuid4());

        if (!$this->lock_event($event_id, 'razorpay')) {
            return ['ok'=>true,'skipped'=>'duplicate'];
        }

        $type = $event['event'];

        switch ($type) {
            case 'payment.captured':
                $this->on_payment_captured($event['payload']['payment']['entity']);
                break;
            case 'payment.failed':
                $this->on_payment_failed($event['payload']['payment']['entity']);
                break;
            case 'order.paid':
                // optional; usually payment.captured already arrives
                break;
            case 'subscription.charged':
                $this->on_subscription_charged($event['payload']['payment']['entity'], $event['payload']['subscription']['entity']);
                break;
            case 'subscription.halted':
            case 'subscription.cancelled':
            case 'subscription.paused':
            case 'subscription.completed':
                $this->on_subscription_status($event['payload']['subscription']['entity']);
                break;
            default:
                // ignore others for now
                break;
        }

        $this->unlock_event($event_id, 'ok', $payload);
        return ['ok'=>true];
    }

    private function on_payment_captured(array $p): void {
        global $wpdb;
        $don = $wpdb->prefix.'fa_donations';

        $payment_id = sanitize_text_field($p['id'] ?? '');
        $order_id   = sanitize_text_field($p['order_id'] ?? '');
        $amount     = ((int)($p['amount'] ?? 0)) / 100;
        $currency   = sanitize_text_field($p['currency'] ?? 'INR');
        $email      = sanitize_email($p['email'] ?? ($p['contact'] ?? ''));
        $notes      = is_array($p['notes'] ?? null) ? $p['notes'] : [];

        // ensure donor user
        $user_id = 0;
        if (!empty($notes['user_id'])) $user_id = (int)$notes['user_id'];
        if (!$user_id && $email){
            $u = get_user_by('email', $email);
            if ($u) $user_id = (int)$u->ID;
        }

        // skip if already inserted
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $don WHERE razorpay_payment_id=%s", $payment_id));
        if ($exists) return;

        $type = sanitize_text_field($notes['type'] ?? 'general');
        $orphan_id = isset($notes['orphan_id']) ? (int)$notes['orphan_id'] : null;
        $cause_id  = isset($notes['cause_id']) ? (int)$notes['cause_id']  : null;

        $name = sanitize_text_field($notes['donor_name'] ?? '');
        $phone= preg_replace('/[^0-9+]/','', (string)($notes['phone'] ?? ''));

        $fy = RazorpayService::fy_from_date(gmdate('Y-m-d'));
        $receipt_no = $this->issue_receipt_no($fy);

        $wpdb->insert($don, [
            'created_at' => current_time('mysql', true),
            'user_id' => $user_id ?: null,
            'donor_name' => $name ?: ($email ?: ''),
            'donor_email'=> $email,
            'phone' => $phone ?: null,
            'amount'=> $amount,
            'currency'=>$currency,
            'type' => $type,
            'orphan_id' => $orphan_id ?: null,
            'cause_id'  => $cause_id ?: null,
            'razorpay_payment_id'=>$payment_id,
            'razorpay_order_id'=>$order_id,
            'status' => 'captured',
            'financial_year' => $fy,
            'receipt_no' => $receipt_no,
            'meta' => maybe_serialize(['notes'=>$notes])
        ]);

        $insert_id = (int)$wpdb->insert_id;

        // Recalculate cause progress (if applicable)
        if ($cause_id) DonationEffects::update_cause_progress((int)$cause_id);

        // If this captured payment was tied to a subscription for an orphan, slots will update in on_subscription_* handlers.
        // For safety, if notes included orphan_id (one-time sponsorship), do NOT mark slots as active.

        // Load the row just inserted to pass to mail/PDF
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $don WHERE id=%d", $insert_id), ARRAY_A);
        if ($row) {
            EmailService::send_thankyou_with_receipt($row);
        }
    }

    private function on_payment_failed(array $p): void {
        global $wpdb;
        $don = $wpdb->prefix.'fa_donations';
        $payment_id = sanitize_text_field($p['id'] ?? '');
        $order_id   = sanitize_text_field($p['order_id'] ?? '');
        $email      = sanitize_email($p['email'] ?? ($p['contact'] ?? ''));
        $notes      = is_array($p['notes'] ?? null) ? $p['notes'] : [];
        $amount     = ((int)($p['amount'] ?? 0)) / 100;
        $currency   = sanitize_text_field($p['currency'] ?? 'INR');

        // Insert only if not present
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $don WHERE razorpay_payment_id=%s", $payment_id));
        if ($exists) return;

        $wpdb->insert($don, [
            'created_at' => current_time('mysql', true),
            'donor_email'=> $email,
            'amount'=> $amount,
            'currency'=>$currency,
            'type' => sanitize_text_field($notes['type'] ?? 'general'),
            'orphan_id' => isset($notes['orphan_id'])?(int)$notes['orphan_id']:null,
            'cause_id'  => isset($notes['cause_id'])?(int)$notes['cause_id']:null,
            'razorpay_payment_id'=>$payment_id,
            'razorpay_order_id'=>$order_id,
            'status' => 'failed',
            'meta' => maybe_serialize(['error'=>$p['error_reason'] ?? '', 'notes'=>$notes])
        ]);
    }

    private function on_subscription_charged(array $payment, array $sub): void {
        // treat like captured donation + update subscriptions table
        $this->on_payment_captured($payment);

        global $wpdb;
        $table = $wpdb->prefix.'fa_subscriptions';
        $sid = sanitize_text_field($sub['id'] ?? '');
        $status = sanitize_text_field($sub['status'] ?? 'active');
        $cur_start = !empty($sub['current_start']) ? gmdate('Y-m-d H:i:s', (int)$sub['current_start']) : null;
        $cur_end   = !empty($sub['current_end'])   ? gmdate('Y-m-d H:i:s', (int)$sub['current_end'])   : null;
        $orphan_id = (int)($sub['notes']['orphan_id'] ?? 0);
        $periodicity = 'monthly'; // Razorpay subs we create below are monthly by default

        $row = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE razorpay_subscription_id=%s", $sid));
        $notes = is_array($sub['notes'] ?? null) ? $sub['notes'] : [];
        $user_id = !empty($notes['user_id']) ? (int)$notes['user_id'] : 0;
        $email = !empty($notes['donor_email']) ? sanitize_email($notes['donor_email']) : '';
        if ($row) {
            $wpdb->update($table, [
                'status'=>$status,
                'current_start'=>$cur_start,
                'current_end'=>$cur_end,
                'orphan_id'=> ($orphan_id ?: null),
                'periodicity'=> $periodicity,
                'notes'=> maybe_serialize($notes)
            ], ['id'=>$row->id]);
        } else {
            $wpdb->insert($table, [
                'razorpay_subscription_id'=>$sid,
                'user_id'=>$user_id ?: null,
                'donor_email'=>$email ?: null,
                'orphan_id'=> ($orphan_id ?: null),
                'periodicity'=> $periodicity,
                'status'=>$status,
                'current_start'=>$cur_start,
                'current_end'=>$cur_end,
                'notes'=> maybe_serialize($notes)
            ]);
        }

        // Update orphan slots if we have an orphan
        if ($orphan_id) {
            DonationEffects::update_orphan_slots((int)$orphan_id);
        }
    }

    private function on_subscription_status(array $sub): void {
        global $wpdb;
        $table = $wpdb->prefix.'fa_subscriptions';
        $sid = sanitize_text_field($sub['id'] ?? '');
        $status = sanitize_text_field($sub['status'] ?? '');
        if (!$sid) return;
        $wpdb->update($table, ['status'=>$status], ['razorpay_subscription_id'=>$sid]);

        $orphan_id = (int)($sub['notes']['orphan_id'] ?? 0);
        if ($orphan_id) {
            DonationEffects::update_orphan_slots($orphan_id);
        }
    }

    /** Reserve event id; return false if already processed */
    private function lock_event(string $event_id, string $handler): bool {
        global $wpdb;
        $ev = $wpdb->prefix.'fa_events';
        $ok = $wpdb->insert($ev, [
            'event_id'=>$event_id,
            'handler'=>$handler,
            'status'=>'locked'
        ]);
        return (bool)$ok;
    }

    private function unlock_event(string $event_id, string $status='ok', string $raw=''): void {
        global $wpdb;
        $ev = $wpdb->prefix.'fa_events';
        $wpdb->update($ev, [
            'processed_at'=> current_time('mysql', true),
            'status'=>$status,
            'raw'=> $raw
        ], ['event_id'=>$event_id]);
    }

    private function issue_receipt_no(string $fy): string {
        $opt = 'fa_receipt_seq_'.$fy;
        $n = (int) get_option($opt, 0);
        $n++;
        update_option($opt, $n, false);
        return sprintf('%s/%06d', $fy, $n);
    }
}
