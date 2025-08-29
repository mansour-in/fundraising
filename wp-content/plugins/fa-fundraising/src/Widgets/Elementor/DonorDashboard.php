<?php
namespace FA\Fundraising\Widgets\Elementor;

use Elementor\Widget_Base;

if (!defined('ABSPATH')) exit;

class DonorDashboard extends Widget_Base {
    public function get_name(){ return 'fa_donor_dashboard'; }
    public function get_title(){ return __('FA Donor Dashboard','fa-fundraising'); }
    public function get_icon(){ return 'eicon-dashboard'; }
    public function get_categories(){ return ['general']; }

    protected function render(){
        echo do_shortcode('[fa_donor_dashboard]');
    }
}
