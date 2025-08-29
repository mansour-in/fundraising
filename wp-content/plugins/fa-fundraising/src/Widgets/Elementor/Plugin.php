<?php
namespace FA\Fundraising\Widgets\Elementor;

if (!defined('ABSPATH')) exit;

class Plugin {
    public function init(): void {
        add_action('elementor/widgets/register', function($widgets_manager){
            require_once __DIR__.'/DonorDashboard.php';
            require_once __DIR__.'/ReceiptsTable.php';
            require_once __DIR__.'/OrphanGrid.php';
            require_once __DIR__.'/DonationCTA.php';
            $widgets_manager->register(new DonorDashboard());
            $widgets_manager->register(new ReceiptsTable());
            $widgets_manager->register(new OrphanGrid());
            $widgets_manager->register(new DonationCTA());
        });
    }
}
