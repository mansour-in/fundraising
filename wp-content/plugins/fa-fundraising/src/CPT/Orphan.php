<?php
namespace FA\Fundraising\CPT;

if (!defined('ABSPATH')) exit;

class Orphan {
    public function init(): void {
        add_action('init', [$this, 'register']);
        add_action('init', [$this, 'register_meta']);
        add_filter('manage_fa_orphan_posts_columns', [$this,'cols']);
        add_action('manage_fa_orphan_posts_custom_column', [$this,'coldata'], 10, 2);
    }

    public function register(): void {
        register_post_type('fa_orphan', [
            'label' => __('Orphans','fa-fundraising'),
            'public' => true,
            'show_in_rest' => true,
            'supports' => ['title','editor','thumbnail','excerpt'],
            'menu_icon' => 'dashicons-groups',
            'rewrite' => ['slug'=>'orphan'],
        ]);
    }

    public function register_meta(): void {
        $meta = [
            'fa_age'             => ['type'=>'integer'],
            'fa_gender'          => ['type'=>'string'],
            'fa_dob'             => ['type'=>'string'],
            'fa_school'          => ['type'=>'string'],
            'fa_grade'           => ['type'=>'string'],
            'fa_story'           => ['type'=>'string'],
            'fa_monthly_cost'    => ['type'=>'number', 'default'=>1500],
            'fa_slots_total'     => ['type'=>'integer','default'=>1],
            'fa_slots_filled'    => ['type'=>'integer','default'=>0],
            'fa_status'          => ['type'=>'string', 'default'=>'unsponsored'],
        ];
        foreach ($meta as $k=>$schema) {
            register_post_meta('fa_orphan', $k, array_merge([
                'single'=>true,
                'show_in_rest'=>true,
                'auth_callback'=>fn()=> current_user_can('edit_posts'),
                'sanitize_callback'=>null
            ], $schema));
        }
    }

    public function cols($cols){
        $cols['fa_age'] = __('Age','fa-fundraising');
        $cols['fa_cost'] = __('Monthly Cost','fa-fundraising');
        $cols['fa_slots'] = __('Slots','fa-fundraising');
        $cols['fa_status'] = __('Status','fa-fundraising');
        return $cols;
    }
    public function coldata($col, $post_id){
        switch($col){
            case 'fa_age': echo esc_html(get_post_meta($post_id,'fa_age',true)); break;
            case 'fa_cost': echo '₹'.number_format((float)get_post_meta($post_id,'fa_monthly_cost',true)); break;
            case 'fa_slots':
                $t = (int)get_post_meta($post_id,'fa_slots_total',true);
                $f = (int)get_post_meta($post_id,'fa_slots_filled',true);
                echo esc_html("$f / $t"); break;
            case 'fa_status': echo esc_html(get_post_meta($post_id,'fa_status',true) ?: 'unsponsored'); break;
        }
    }
}
