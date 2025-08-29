<?php
namespace FA\Fundraising\CPT;

if (!defined('ABSPATH')) exit;

class Cause {
    public function init(): void {
        add_action('init', [$this, 'register']);
        add_action('init', [$this, 'register_meta']);
        add_filter('manage_fa_cause_posts_columns', [$this,'cols']);
        add_action('manage_fa_cause_posts_custom_column', [$this,'coldata'], 10, 2);
    }

    public function register(): void {
        register_post_type('fa_cause', [
            'label' => __('Causes','fa-fundraising'),
            'public' => true,
            'show_in_rest' => true,
            'supports' => ['title','editor','thumbnail','excerpt'],
            'menu_icon' => 'dashicons-megaphone',
            'rewrite' => ['slug'=>'cause'],
        ]);
    }

    public function register_meta(): void {
        $meta = [
            'fa_goal_amount'   => ['type'=>'number','default'=>0],
            'fa_raised_amount' => ['type'=>'number','default'=>0],
            'fa_banner'        => ['type'=>'string'],
            'fa_active'        => ['type'=>'boolean','default'=>true],
        ];
        foreach ($meta as $k=>$schema) {
            register_post_meta('fa_cause', $k, array_merge([
                'single'=>true,
                'show_in_rest'=>true,
                'auth_callback'=>fn()=> current_user_can('edit_posts'),
                'sanitize_callback'=>null
            ], $schema));
        }
    }

    public function cols($cols){
        $cols['fa_goal'] = __('Goal','fa-fundraising');
        $cols['fa_raised'] = __('Raised','fa-fundraising');
        $cols['fa_active'] = __('Active','fa-fundraising');
        return $cols;
    }
    public function coldata($col, $post_id){
        switch($col){
            case 'fa_goal': echo '₹'.number_format((float)get_post_meta($post_id,'fa_goal_amount',true)); break;
            case 'fa_raised': echo '₹'.number_format((float)get_post_meta($post_id,'fa_raised_amount',true)); break;
            case 'fa_active': echo get_post_meta($post_id,'fa_active',true) ? 'Yes' : 'No'; break;
        }
    }
}
