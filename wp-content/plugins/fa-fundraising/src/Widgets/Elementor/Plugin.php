<?php
namespace FA\Fundraising\Widgets\Elementor;

if (!defined('ABSPATH')) exit;

class Plugin {
    public function init(): void {
        add_action('elementor/widgets/register', function($widgets_manager){
            require_once __DIR__.'/DonorDashboard.php';
            require_once __DIR__.'/ReceiptsTable.php';
            $widgets_manager->register(new DonorDashboard());
            $widgets_manager->register(new ReceiptsTable());
        });
    }
}
