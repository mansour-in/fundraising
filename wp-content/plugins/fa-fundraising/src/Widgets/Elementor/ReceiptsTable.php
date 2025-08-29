<?php
namespace FA\Fundraising\Widgets\Elementor;

use Elementor\Widget_Base;

if (!defined('ABSPATH')) exit;

class ReceiptsTable extends Widget_Base {
    public function get_name(){ return 'fa_receipts_table'; }
    public function get_title(){ return __('FA Receipts Table','fa-fundraising'); }
    public function get_icon(){ return 'eicon-table'; }
    public function get_categories(){ return ['general']; }

    protected function render(){
        echo do_shortcode('[fa_receipts]');
    }
}
