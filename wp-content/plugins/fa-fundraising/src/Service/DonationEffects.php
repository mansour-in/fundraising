<?php
namespace FA\Fundraising\Service;

if (!defined('ABSPATH')) exit;

class DonationEffects {

    /** Recompute and store cause "raised" from captured donations */
    public static function update_cause_progress(int $cause_id): void {
        if ($cause_id <= 0) return;
        global $wpdb;
        $don = $wpdb->prefix.'fa_donations';
        $sum = (float)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount),0) FROM $don WHERE cause_id=%d AND status='captured'", $cause_id
        ));
        update_post_meta($cause_id, 'fa_raised_amount', $sum);
    }

    /** Recompute orphan slots_filled from ACTIVE subscriptions tagged with this orphan_id */
    public static function update_orphan_slots(int $orphan_id): void {
        if ($orphan_id <= 0) return;
        global $wpdb;
        $sub = $wpdb->prefix.'fa_subscriptions';
        // Count active subs for this orphan
        $cnt = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sub WHERE orphan_id=%d AND status='active'", $orphan_id
        ));
        update_post_meta($orphan_id, 'fa_slots_filled', $cnt);

        // Auto-compute status based on slots (unsponsored/partial/full)
        $total = (int)get_post_meta($orphan_id, 'fa_slots_total', true);
        $status = 'unsponsored';
        if ($total > 0) {
            if ($cnt >= $total) $status = 'full';
            elseif ($cnt > 0)   $status = 'partial';
        }
        update_post_meta($orphan_id, 'fa_status', $status);
    }
}
