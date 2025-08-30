<?php
namespace FA\Fundraising\Widgets\Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) exit;

class CauseProgress extends Widget_Base {
    public function get_name(){ return 'fa_cause_progress'; }
    public function get_title(){ return __('FA Cause Progress','fa-fundraising'); }
    public function get_icon(){ return 'eicon-skill-bar'; }
    public function get_categories(){ return ['general']; }

    protected function register_controls() {
        $this->start_controls_section('cfg', ['label'=>__('Settings','fa-fundraising')]);
        $this->add_control('cause_id', ['label'=>__('Cause ID (leave 0 to use current post)','fa-fundraising'),'type'=>Controls_Manager::NUMBER,'default'=>0]);
        $this->add_control('show_numbers', ['label'=>__('Show Numbers'),'type'=>Controls_Manager::SWITCHER,'default'=>'yes']);
        $this->end_controls_section();
    }

    protected function render() {
        $cid = (int)($this->get_settings('cause_id') ?: 0);
        if (!$cid && get_post_type() === 'fa_cause') $cid = get_the_ID();
        if (!$cid) { echo '<div>'.esc_html__('Select a Cause','fa-fundraising').'</div>'; return; }

        $goal = (float)get_post_meta($cid,'fa_goal_amount',true);
        $raised = (float)get_post_meta($cid,'fa_raised_amount',true);
        $pct = $goal > 0 ? min(100, round(($raised/$goal)*100)) : 0;

        $show = $this->get_settings('show_numbers') === 'yes';

        ?>
        <div class="fa-card" style="border:1px solid #e5e7eb;border-radius:12px;padding:14px;background:#fff;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <strong><?php echo esc_html(get_the_title($cid)); ?></strong>
                <?php if ($show): ?>
                    <span style="opacity:.8;"><?php echo esc_html($pct); ?>%</span>
                <?php endif; ?>
            </div>
            <div style="height:14px;border-radius:999px;background:#f1f5f9;overflow:hidden;">
                <div style="height:100%;width:<?php echo (int)$pct; ?>%;background:#111;border-radius:999px;transition:width .6s;"></div>
            </div>
            <?php if ($show): ?>
            <div style="display:flex;justify-content:space-between;opacity:.8;margin-top:6px;font-size:.95rem;">
                <span><?php esc_html_e('Raised','fa-fundraising'); ?>: ₹<?php echo number_format((int)$raised); ?></span>
                <span><?php esc_html_e('Goal','fa-fundraising'); ?>: ₹<?php echo number_format((int)$goal); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
