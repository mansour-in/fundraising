<?php
namespace FA\Fundraising\Api;

if (!defined('ABSPATH')) exit;

class StatsController {

    public function init(): void {
        add_action('rest_api_init', function(){
            register_rest_route('faf/v1', '/stats/summary', [
                'methods'  => 'GET',
                'callback' => [$this, 'summary'],
                'permission_callback' => function(){ return is_user_logged_in(); }
            ]);
            register_rest_route('faf/v1', '/stats/series', [
                'methods'  => 'GET',
                'callback' => [$this, 'series'],
                'permission_callback' => function(){ return is_user_logged_in(); }
            ]);
        });
    }

    public function summary(\WP_REST_Request $req) {
        $uid = get_current_user_id();
        global $wpdb;
        $don = $wpdb->prefix.'fa_donations';
        $sps = $wpdb->prefix.'fa_sponsorships';

        // Lifetime total
        $lifetime = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount),0) FROM $don WHERE user_id=%d AND status='captured'",
            $uid
        ));

        // Month-to-date and last month totals
        $now  = current_time('timestamp', true); // UTC
        $ym   = gmdate('Y-m-01 00:00:00', $now);
        $mtd  = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount),0) FROM $don WHERE user_id=%d AND status='captured' AND created_at >= %s",
            $uid, $ym
        ));

        $first_prev = gmdate('Y-m-01 00:00:00', strtotime('first day of previous month', $now));
        $first_curr = gmdate('Y-m-01 00:00:00', $now);
        $last_month = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount),0) FROM $don WHERE user_id=%d AND status='captured' AND created_at >= %s AND created_at < %s",
            $uid, $first_prev, $first_curr
        ));

        // Active sponsorships count
        $active_sponsorships = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sps WHERE user_id=%d AND status='active'",
            $uid
        ));

        // Breakdown by type (amounts & counts)
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT type, COUNT(*) c, COALESCE(SUM(amount),0) s
             FROM $don WHERE user_id=%d AND status='captured'
             GROUP BY type", $uid
        ), ARRAY_A);

        $breakdown = ['general'=>['count'=>0,'amount'=>0.0],
                      'cause'=>['count'=>0,'amount'=>0.0],
                      'sponsorship'=>['count'=>0,'amount'=>0.0]];
        foreach ($rows as $r) {
            $t = $r['type'];
            if (!isset($breakdown[$t])) $breakdown[$t] = ['count'=>0,'amount'=>0.0];
            $breakdown[$t]['count']  = (int)$r['c'];
            $breakdown[$t]['amount'] = (float)$r['s'];
        }

        return [
            'ok'=>true,
            'lifetime_total'=>$lifetime,
            'month_to_date'=>$mtd,
            'last_month'=>$last_month,
            'active_sponsorships'=>$active_sponsorships,
            'breakdown'=>$breakdown
        ];
    }

    public function series(\WP_REST_Request $req) {
        $uid = get_current_user_id();
        global $wpdb;
        $don = $wpdb->prefix.'fa_donations';

        // Last 12 months, sum per month
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(created_at,'%%Y-%%m-01') m, COALESCE(SUM(amount),0) s
             FROM $don
             WHERE user_id=%d AND status='captured'
               AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 12 MONTH)
             GROUP BY m
             ORDER BY m ASC", $uid
        ), ARRAY_A);

        // Build full 12-slot series
        $labels = [];
        $data   = [];
        $map = [];
        foreach ($rows as $r) $map[$r['m']] = (float)$r['s'];

        for ($i=11; $i>=0; $i--) {
            $ym = gmdate('Y-m-01', strtotime("-$i months"));
            $labels[] = gmdate('M Y', strtotime($ym));
            $data[]   = isset($map[$ym]) ? $map[$ym] : 0.0;
        }

        return ['ok'=>true, 'labels'=>$labels, 'data'=>$data];
    }
}
