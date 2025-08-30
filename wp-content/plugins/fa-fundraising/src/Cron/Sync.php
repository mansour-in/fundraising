<?php
namespace FA\Fundraising\Cron;

use FA\Fundraising\Service\DonationEffects;

if (!defined('ABSPATH')) exit;

class Sync {
    public function init(): void {
        add_action('fa_nightly_sync', [$this,'run']);

        // Schedule on plugin load if not scheduled
        if (!wp_next_scheduled('fa_nightly_sync')) {
            wp_schedule_event(time() + 3600, 'daily', 'fa_nightly_sync');
        }
    }

    public function run(): void {
        // Causes
        $causes = get_posts(['post_type'=>'fa_cause','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids']);
        foreach ($causes as $cid) DonationEffects::update_cause_progress((int)$cid);

        // Orphans
        $orphans = get_posts(['post_type'=>'fa_orphan','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids']);
        foreach ($orphans as $oid) DonationEffects::update_orphan_slots((int)$oid);
    }
}
