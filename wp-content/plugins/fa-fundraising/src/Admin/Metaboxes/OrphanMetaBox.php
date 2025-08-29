<?php
namespace FA\Fundraising\Admin\Metaboxes;

if (!defined('ABSPATH')) exit;

class OrphanMetaBox {
    public function init(): void {
        add_action('add_meta_boxes', [$this,'register']);
        add_action('save_post_fa_orphan', [$this,'save'], 10, 2);
    }

    public function register(): void {
        add_meta_box(
            'fa_orphan_details',
            __('Orphan Details','fa-fundraising'),
            [$this,'render'],
            'fa_orphan',
            'normal',
            'high'
        );
    }

    public function render(\WP_Post $post): void {
        wp_nonce_field('fa_orphan_meta', 'fa_orphan_meta_nonce');

        $age          = (int) get_post_meta($post->ID, 'fa_age', true);
        $gender       = (string) get_post_meta($post->ID, 'fa_gender', true);
        $dob          = (string) get_post_meta($post->ID, 'fa_dob', true);
        $school       = (string) get_post_meta($post->ID, 'fa_school', true);
        $grade        = (string) get_post_meta($post->ID, 'fa_grade', true);
        $monthly_cost = (float) get_post_meta($post->ID, 'fa_monthly_cost', true);
        $slots_total  = (int) get_post_meta($post->ID, 'fa_slots_total', true);
        $slots_filled = (int) get_post_meta($post->ID, 'fa_slots_filled', true);
        $status       = (string) (get_post_meta($post->ID, 'fa_status', true) ?: 'unsponsored');

        ?>
        <style>
            .fa-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
            .fa-grid .col{display:flex;flex-direction:column;gap:6px;}
            .fa-help{opacity:.7;font-size:12px}
            @media (max-width:900px){.fa-grid{grid-template-columns:1fr}}
            .fa-input{padding:.5rem;border:1px solid #ccd0d4;border-radius:6px}
        </style>

        <div class="fa-grid">
            <div class="col">
                <label><?php esc_html_e('Age','fa-fundraising'); ?></label>
                <input class="fa-input" type="number" min="0" name="fa_age" value="<?php echo esc_attr($age); ?>">
            </div>
            <div class="col">
                <label><?php esc_html_e('Gender','fa-fundraising'); ?></label>
                <select class="fa-input" name="fa_gender">
                    <?php foreach (['','Male','Female','Other'] as $g): ?>
                        <option value="<?php echo esc_attr($g); ?>" <?php selected($gender,$g); ?>><?php echo esc_html($g?:'—'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col">
                <label><?php esc_html_e('Date of Birth','fa-fundraising'); ?></label>
                <input class="fa-input" type="date" name="fa_dob" value="<?php echo esc_attr($dob); ?>">
            </div>
            <div class="col">
                <label><?php esc_html_e('Monthly Cost (INR)','fa-fundraising'); ?></label>
                <input class="fa-input" type="number" step="0.01" min="0" name="fa_monthly_cost" value="<?php echo esc_attr($monthly_cost ?: 1500); ?>">
            </div>

            <div class="col">
                <label><?php esc_html_e('Slots Total','fa-fundraising'); ?></label>
                <input class="fa-input" type="number" min="0" name="fa_slots_total" value="<?php echo esc_attr($slots_total ?: 1); ?>">
                <div class="fa-help"><?php esc_html_e('Number of sponsorship slots available for this child.','fa-fundraising'); ?></div>
            </div>
            <div class="col">
                <label><?php esc_html_e('Slots Filled','fa-fundraising'); ?></label>
                <input class="fa-input" type="number" min="0" name="fa_slots_filled" value="<?php echo esc_attr($slots_filled ?: 0); ?>">
                <div class="fa-help"><?php esc_html_e('How many slots are currently sponsored.','fa-fundraising'); ?></div>
            </div>

            <div class="col">
                <label><?php esc_html_e('Status','fa-fundraising'); ?></label>
                <select class="fa-input" name="fa_status">
                    <option value="auto"><?php esc_html_e('Auto (based on slots)','fa-fundraising'); ?></option>
                    <option value="unsponsored" <?php selected($status,'unsponsored'); ?>><?php esc_html_e('Unsponsored','fa-fundraising'); ?></option>
                    <option value="partial" <?php selected($status,'partial'); ?>><?php esc_html_e('Partial','fa-fundraising'); ?></option>
                    <option value="full" <?php selected($status,'full'); ?>><?php esc_html_e('Full','fa-fundraising'); ?></option>
                </select>
                <div class="fa-help"><?php esc_html_e('Choose “Auto” to compute from slots (recommended).','fa-fundraising'); ?></div>
            </div>
            <div class="col"></div>

            <div class="col">
                <label><?php esc_html_e('School','fa-fundraising'); ?></label>
                <input class="fa-input" type="text" name="fa_school" value="<?php echo esc_attr($school); ?>">
            </div>
            <div class="col">
                <label><?php esc_html_e('Grade/Class','fa-fundraising'); ?></label>
                <input class="fa-input" type="text" name="fa_grade" value="<?php echo esc_attr($grade); ?>">
            </div>
        </div>

        <p class="fa-help" style="margin-top:8px;">
            <?php esc_html_e('Assign District from the “District” taxonomy box in the sidebar.','fa-fundraising'); ?>
        </p>
        <?php
    }

    public function save(int $post_id, \WP_Post $post): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['fa_orphan_meta_nonce']) || !wp_verify_nonce($_POST['fa_orphan_meta_nonce'], 'fa_orphan_meta')) return;
        if (!current_user_can('edit_post', $post_id)) return;

        // Sanitize
        $age          = isset($_POST['fa_age']) ? max(0, (int)$_POST['fa_age']) : 0;
        $gender       = isset($_POST['fa_gender']) ? sanitize_text_field($_POST['fa_gender']) : '';
        $dob          = isset($_POST['fa_dob']) ? sanitize_text_field($_POST['fa_dob']) : '';
        $monthly_cost = isset($_POST['fa_monthly_cost']) ? max(0, (float)$_POST['fa_monthly_cost']) : 0.0;
        $slots_total  = isset($_POST['fa_slots_total']) ? max(0, (int)$_POST['fa_slots_total']) : 0;
        $slots_filled = isset($_POST['fa_slots_filled']) ? max(0, (int)$_POST['fa_slots_filled']) : 0;
        $sel_status   = isset($_POST['fa_status']) ? sanitize_text_field($_POST['fa_status']) : 'auto';
        $school       = isset($_POST['fa_school']) ? sanitize_text_field($_POST['fa_school']) : '';
        $grade        = isset($_POST['fa_grade']) ? sanitize_text_field($_POST['fa_grade']) : '';

        // Compute status if 'auto'
        $computed = 'unsponsored';
        if ($slots_total > 0) {
            if ($slots_filled >= $slots_total) $computed = 'full';
            elseif ($slots_filled > 0) $computed = 'partial';
            else $computed = 'unsponsored';
        }
        $status = ($sel_status === 'auto') ? $computed : $sel_status;

        // Persist
        update_post_meta($post_id, 'fa_age', $age);
        update_post_meta($post_id, 'fa_gender', $gender);
        update_post_meta($post_id, 'fa_dob', $dob);
        update_post_meta($post_id, 'fa_monthly_cost', $monthly_cost);
        update_post_meta($post_id, 'fa_slots_total', $slots_total);
        update_post_meta($post_id, 'fa_slots_filled', min($slots_filled, $slots_total));
        update_post_meta($post_id, 'fa_status', $status);
        update_post_meta($post_id, 'fa_school', $school);
        update_post_meta($post_id, 'fa_grade', $grade);
    }
}
