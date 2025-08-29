<?php
namespace FA\Fundraising\Api;

use FA\Fundraising\Pdf\Renderer;

if (!defined('ABSPATH')) exit;

class DonationController {
    public function init(): void {
        add_action('rest_api_init', function(){
            register_rest_route('faf/v1', '/donations', [
                'methods'=>'GET',
                'callback'=>[$this,'list_donations'],
                'permission_callback'=>function(){ return is_user_logged_in(); }
            ]);
            register_rest_route('faf/v1', '/subscriptions', [
                'methods'=>'GET',
                'callback'=>[$this,'list_subscriptions'],
                'permission_callback'=>function(){ return is_user_logged_in(); }
            ]);
            register_rest_route('faf/v1', '/receipt/(?P<id>\d+)', [
                'methods'=>'GET',
                'callback'=>[$this,'download_receipt'],
                'permission_callback'=>function(){ return is_user_logged_in(); }
            ]);
            register_rest_route('faf/v1', '/verify/receipt', [
                'methods'=>'GET',
                'callback'=>[$this,'verify_receipt'],
                'permission_callback'=>'__return_true'
            ]);
        });
    }

    public function list_donations(\WP_REST_Request $req) {
        global $wpdb;
        $uid = get_current_user_id();
        $don = $wpdb->prefix.'fa_donations';

        $page = max(1, (int)$req->get_param('page'));
        $per  = min(50, max(1, (int)($req->get_param('per_page') ?: 10)));
        $off  = ($page-1) * $per;

        $status = sanitize_text_field($req->get_param('status') ?: '');
        $type   = sanitize_text_field($req->get_param('type') ?: '');
        $start  = sanitize_text_field($req->get_param('start') ?: '');
        $end    = sanitize_text_field($req->get_param('end') ?: '');

        $where = $wpdb->prepare("WHERE user_id=%d", $uid);
        if ($status) $where .= $wpdb->prepare(" AND status=%s", $status);
        if ($type)   $where .= $wpdb->prepare(" AND type=%s", $type);
        if ($start)  $where .= $wpdb->prepare(" AND created_at >= %s", $start.' 00:00:00');
        if ($end)    $where .= $wpdb->prepare(" AND created_at <= %s", $end.' 23:59:59');

        $items = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS id, created_at, type, amount, currency, status, orphan_id, cause_id, razorpay_payment_id, receipt_pdf, receipt80g_pdf
           FROM $don $where ORDER BY id DESC LIMIT $off,$per", ARRAY_A);
        $total = (int)$wpdb->get_var("SELECT FOUND_ROWS()");

        foreach ($items as &$it) {
            $it['receipt_basic_available'] = (bool)$it['receipt_pdf'];
            $it['receipt_80g_available']   = (bool)$it['receipt80g_pdf'];
        }

        return ['ok'=>true, 'items'=>$items, 'page'=>$page, 'per_page'=>$per, 'total'=>$total];
    }

    public function list_subscriptions(\WP_REST_Request $req) {
        global $wpdb;
        $uid = get_current_user_id();
        $sub = $wpdb->prefix.'fa_subscriptions';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, razorpay_subscription_id, status, plan_id, current_start, current_end, notes
             FROM $sub WHERE user_id=%d ORDER BY id DESC", $uid
        ), ARRAY_A);

        // Optional pretty fields
        foreach ($rows as &$r) {
            $notes = maybe_unserialize($r['notes']);
            $r['manage_url'] = is_array($notes) && !empty($notes['manage_url']) ? esc_url_raw($notes['manage_url']) : '';
        }
        return ['ok'=>true, 'items'=>$rows];
    }

    public function download_receipt(\WP_REST_Request $req) {
        global $wpdb;
        $uid = get_current_user_id();
        $id  = (int)$req->get_param('id');
        $type = $req->get_param('type') === '80g' ? '80g' : 'basic';

        $don = $wpdb->prefix.'fa_donations';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $don WHERE id=%d AND user_id=%d", $id, $uid), ARRAY_A);
        if (!$row) return new \WP_Error('not_found','Receipt not found',['status'=>404]);

        $path_col = $type === '80g' ? 'receipt80g_pdf' : 'receipt_pdf';

        if (empty($row[$path_col]) || !file_exists($row[$path_col])) {
            // generate
            $path = Renderer::generate($row, $type);
            $wpdb->update($don, [$path_col => $path], ['id'=>$row['id']]);
        } else {
            $path = $row[$path_col];
        }

        if (!is_readable($path)) return new \WP_Error('file','Cannot read receipt',['status'=>500]);

        // Stream file
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="'.basename($path).'"');
        header('Content-Length: '.filesize($path));
        readfile($path);
        exit;
    }

    public function verify_receipt(\WP_REST_Request $req) {
        global $wpdb;
        $pid = sanitize_text_field($req->get_param('payment_id'));
        if (!$pid) return new \WP_Error('bad','payment_id required',['status'=>400]);
        $don = $wpdb->prefix.'fa_donations';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, donor_name, donor_email, amount, currency, status, created_at FROM $don WHERE razorpay_payment_id=%s", $pid
        ), ARRAY_A);
        if (!$row) return new \WP_Error('not_found','Not found',['status'=>404]);
        return ['ok'=>true,'receipt'=>$row];
    }
}
