<?php
namespace FA\Fundraising\CPT;

if (!defined('ABSPATH')) exit;

class Taxonomies {
    public function init(): void {
        add_action('init', [$this, 'district']);
    }

    public function district(): void {
        register_taxonomy('fa_district', ['fa_orphan'], [
            'label' => __('District','fa-fundraising'),
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'rewrite' => ['slug'=>'district'],
            'show_in_rest' => true,
        ]);
    }
}
