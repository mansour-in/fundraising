<?php
namespace FA\Fundraising\Admin\Metaboxes;

if (!defined('ABSPATH')) exit;

class CauseMetaBox {
    public function init(): void {
        add_action('add_meta_boxes', [$this,'register']);
        add_action('save_post_fa_cause', [$this,'save'], 10, 2);
    }

    public function register(): void {
        add_meta_box(
            'fa_cause_details',
            __('Cause Details','fa-fundraising'),
            [$this,'render'],
            'fa_cause',
            'normal',
            'high'
        );
    }

    public function render(\WP_Post $post): void {
        wp_nonce_field('fa_cause_meta', 'fa_cause_meta_nonce');

        $goal   = (float) get_post_meta($post->ID, 'fa_goal_amount', true);
        $raised = (float) get_post_meta($post->ID, 'fa_raised_amount', true);
        $active = (bool)  get_post_meta($post->ID, 'fa_active', true);

        ?>
        <style>.fa-input{padding:.5rem;border:1px solid #ccd0d4;border-radius:6px} .fa-help{opacity:.7;font-size:12px}</style>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
                <label><?php esc_html_e('Goal Amount (INR)','fa-fundraising'); ?></label>
                <input class="fa-input" type="number" step="0.01" min="0" name="fa_goal_amount" value="<?php echo esc_attr($goal ?: 0); ?>">
            </div>
            <div>
                <label><?php esc_html_e('Raised Amount (INR)','fa-fundraising'); ?></label>
                <input class="fa-input" type="number" step="0.01" min="0" name="fa_raised_amount" value="<?php echo esc_attr($raised ?: 0); ?>">
                <div class="fa-help"><?php esc_html_e('This will auto-update from donations in a later step. You can override manually here.','fa-fundraising'); ?></div>
            </div>
            <div style="grid-column:1/-1;display:flex;align-items:center;gap:8px;margin-top:6px;">
                <label><input type="checkbox" name="fa_active" value="1" <?php checked($active, true); ?>> <?php esc_html_e('Active','fa-fundraising'); ?></label>
            </div>
        </div>
        <?php
    }

    public function save(int $post_id, \WP_Post $post): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['fa_cause_meta_nonce']) || !wp_verify_nonce($_POST['fa_cause_meta_nonce'], 'fa_cause_meta')) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $goal   = isset($_POST['fa_goal_amount']) ? max(0, (float)$_POST['fa_goal_amount']) : 0.0;
        $raised = isset($_POST['fa_raised_amount']) ? max(0, (float)$_POST['fa_raised_amount']) : 0.0;
        $active = isset($_POST['fa_active']) ? 1 : 0;

        update_post_meta($post_id, 'fa_goal_amount', $goal);
        update_post_meta($post_id, 'fa_raised_amount', $raised);
        update_post_meta($post_id, 'fa_active', $active);
    }
}
