<?php
namespace FA\Fundraising\Api;

if (!defined('ABSPATH')) exit;

class DirectoryController {
    public function init(): void {
        add_action('rest_api_init', function(){
            register_rest_route('faf/v1','/orphans',[
                'methods'=>'GET',
                'callback'=>[$this,'orphans'],
                'permission_callback'=>'__return_true'
            ]);
            register_rest_route('faf/v1','/orphans/(?P<id>\d+)',[
                'methods'=>'GET',
                'callback'=>[$this,'orphan_single'],
                'permission_callback'=>'__return_true'
            ]);
            register_rest_route('faf/v1','/causes',[
                'methods'=>'GET',
                'callback'=>[$this,'causes'],
                'permission_callback'=>'__return_true'
            ]);
            register_rest_route('faf/v1','/causes/(?P<id>\d+)',[
                'methods'=>'GET',
                'callback'=>[$this,'cause_single'],
                'permission_callback'=>'__return_true'
            ]);
        });
    }

    public function orphans(\WP_REST_Request $r){
        $page = max(1,(int)$r->get_param('page'));
        $per  = min(50,max(1,(int)($r->get_param('per_page')?:12)));
        $args = [
            'post_type'=>'fa_orphan',
            'post_status'=>'publish',
            'paged'=>$page,
            'posts_per_page'=>$per,
        ];

        if ($s = sanitize_text_field($r->get_param('search'))) $args['s'] = $s;

        if ($d = sanitize_text_field($r->get_param('district'))) {
            $args['tax_query'] = [[
                'taxonomy'=>'fa_district','field'=>'name','terms'=>$d
            ]];
        }

        $meta_query = [];
        if ($st = sanitize_text_field($r->get_param('status'))) {
            $meta_query[] = ['key'=>'fa_status','value'=>$st,'compare'=>'='];
        }
        $amin = (int)$r->get_param('age_min'); $amax = (int)$r->get_param('age_max');
        if ($amin) $meta_query[] = ['key'=>'fa_age','value'=>$amin,'compare'=>'>=','type'=>'NUMERIC'];
        if ($amax) $meta_query[] = ['key'=>'fa_age','value'=>$amax,'compare'=>'<=','type'=>'NUMERIC'];
        if ($meta_query) $args['meta_query'] = $meta_query;

        $q = new \WP_Query($args);
        $items = [];
        foreach ($q->posts as $p) {
            $items[] = $this->orphan_payload($p->ID);
        }
        return [
            'ok'=>true,
            'items'=>$items,
            'page'=>$page,
            'per_page'=>$per,
            'total'=>(int)$q->found_posts
        ];
    }

    public function orphan_single(\WP_REST_Request $r){
        $id = (int)$r->get_param('id');
        if (!$id || get_post_type($id)!=='fa_orphan' || get_post_status($id)!=='publish')
            return new \WP_Error('not_found','Not found',['status'=>404]);
        return ['ok'=>true,'item'=>$this->orphan_payload($id)];
    }

    private function orphan_payload(int $id): array {
        $dists = wp_get_post_terms($id,'fa_district', ['fields'=>'names']);
        return [
            'id'=>$id,
            'name'=>get_the_title($id),
            'excerpt'=>wp_strip_all_tags(get_the_excerpt($id)),
            'image'=> get_the_post_thumbnail_url($id,'medium') ?: '',
            'districts'=>$dists,
            'age'=> (int)get_post_meta($id,'fa_age',true),
            'gender'=> (string)get_post_meta($id,'fa_gender',true),
            'monthly_cost'=> (float)get_post_meta($id,'fa_monthly_cost',true),
            'slots_total'=> (int)get_post_meta($id,'fa_slots_total',true),
            'slots_filled'=> (int)get_post_meta($id,'fa_slots_filled',true),
            'status'=> (string)get_post_meta($id,'fa_status',true) ?: 'unsponsored',
        ];
    }

    public function causes(\WP_REST_Request $r){
        $page = max(1,(int)$r->get_param('page'));
        $per  = min(50,max(1,(int)($r->get_param('per_page')?:12)));
        $args = [
            'post_type'=>'fa_cause',
            'post_status'=>'publish',
            'paged'=>$page,
            'posts_per_page'=>$per,
        ];
        if ($s = sanitize_text_field($r->get_param('search'))) $args['s'] = $s;
        if (null !== ($active = $r->get_param('active'))) {
            $args['meta_query'][] = ['key'=>'fa_active','value' => (int)!!$active, 'compare'=>'='];
        }
        $q = new \WP_Query($args);
        $items = [];
        foreach ($q->posts as $p) {
            $id = $p->ID;
            $items[] = [
                'id'=>$id,
                'title'=>get_the_title($id),
                'excerpt'=>wp_strip_all_tags(get_the_excerpt($id)),
                'image'=> get_the_post_thumbnail_url($id,'medium') ?: '',
                'goal'=> (float)get_post_meta($id,'fa_goal_amount',true),
                'raised'=> (float)get_post_meta($id,'fa_raised_amount',true),
                'active'=> (bool)get_post_meta($id,'fa_active',true),
            ];
        }
        return ['ok'=>true,'items'=>$items,'page'=>$page,'per_page'=>$per,'total'=>(int)$q->found_posts];
    }

    public function cause_single(\WP_REST_Request $r){
        $id = (int)$r->get_param('id');
        if (!$id || get_post_type($id)!=='fa_cause' || get_post_status($id)!=='publish')
            return new \WP_Error('not_found','Not found',['status'=>404]);
        $item = [
            'id'=>$id,
            'title'=>get_the_title($id),
            'content'=>wp_kses_post(get_post_field('post_content',$id)),
            'goal'=> (float)get_post_meta($id,'fa_goal_amount',true),
            'raised'=> (float)get_post_meta($id,'fa_raised_amount',true),
            'active'=> (bool)get_post_meta($id,'fa_active',true),
        ];
        return ['ok'=>true,'item'=>$item];
    }
}
