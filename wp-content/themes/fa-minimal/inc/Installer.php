<?php
if (!defined('ABSPATH')) exit;

class FA_Minimal_Installer {

    const FLAG = 'fa_minimal_oneclick_done';
    const PLUGIN_FILE = 'fa-fundraising/fa-fundraising.php'; // Adjust if your main file differs

    public static function run() {
        if (get_option(self::FLAG)) return; // idempotent
        self::install_and_activate_plugin();
        $ids = self::create_core_pages();
        self::wire_options($ids);
        self::ensure_menu($ids);
        flush_rewrite_rules();
        update_option(self::FLAG, gmdate('c'));
        add_action('admin_notices', function(){
            echo '<div class="notice notice-success"><p><strong>FA Minimal:</strong> Installed FA Fundraising & seeded pages.</p></div>';
        });
    }

    public static function run_cli() {
        self::run();
        if (defined('WP_CLI') && WP_CLI) \WP_CLI::success('One-click installer completed.');
    }

    private static function install_and_activate_plugin(): void {
        if (is_plugin_active(self::PLUGIN_FILE)) return;

        // If already installed but inactive, just activate
        if (file_exists(WP_PLUGIN_DIR . '/' . self::PLUGIN_FILE)) {
            activate_plugin(self::PLUGIN_FILE);
            return;
        }

        // Install from bundled ZIP
        $zip = get_template_directory() . '/bundled/fa-fundraising.zip';
        if (!file_exists($zip)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p><strong>FA Minimal:</strong> Missing bundled plugin ZIP at <code>theme/bundled/fa-fundraising.zip</code>.</p></div>';
            });
            return;
        }

        require_once ABSPATH.'wp-admin/includes/file.php';
        require_once ABSPATH.'wp-admin/includes/plugin.php';
        require_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';

        WP_Filesystem();
        $skin = new Automatic_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);
        $result = $upgrader->install($zip);

        if (is_wp_error($result)) {
            add_action('admin_notices', function() use ($result){
                echo '<div class="notice notice-error"><p>Plugin install failed: '.esc_html($result->get_error_message()).'</p></div>';
            });
            return;
        }

        // Activate
        $activate = activate_plugin(self::PLUGIN_FILE);
        if (is_wp_error($activate)) {
            add_action('admin_notices', function() use ($activate){
                echo '<div class="notice notice-error"><p>Plugin activation failed: '.esc_html($activate->get_error_message()).'</p></div>';
            });
        }
    }

    private static function create_core_pages(): array {
        $pages = [
            'orphans'         => ['Orphans','page-orphan-directory.php','[fa_orphan_grid per_page="12"]'],
            'donor-dashboard' => ['Donor Dashboard','page-donor-dashboard.php','[fa_donor_dashboard]'],
            'donor-receipts'  => ['Donor Receipts','page-donor-receipts.php','[fa_receipts]'],
            'verify-receipt'  => ['Verify Receipt','page-verify-receipt.php','[fa_verify_receipt]'],
            'donor-login'     => ['Donor Login','page-donor-login.php',''], // template renders form
        ];

        $ids = [];
        foreach ($pages as $slug=>$def) {
            [$title,$template,$content] = $def;
            $page = get_page_by_path($slug);
            if ($page) { $ids[$slug] = (int)$page->ID; continue; }

            $id = wp_insert_post([
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => $content,
            ]);
            if ($id && !is_wp_error($id)) {
                if ($template) update_post_meta($id, '_wp_page_template', $template);
                $ids[$slug] = (int)$id;
            }
        }
        return $ids;
    }

    private static function wire_options(array $ids): void {
        // Wire plugin page options if present
        if (!empty($ids['donor-dashboard'])) update_option('fa_donor_dashboard_page_id', (int)$ids['donor-dashboard'], false);
        if (!empty($ids['donor-receipts']))  update_option('fa_donor_receipts_page_id',  (int)$ids['donor-receipts'],  false);
        if (!empty($ids['donor-login']))     update_option('fa_donor_login_page_id',     (int)$ids['donor-login'],     false);

        // Optional: set Orphans as front page on first run
        if (!get_option('show_on_front')) {
            if (!empty($ids['orphans'])) {
                update_option('show_on_front','page', false);
                update_option('page_on_front', (int)$ids['orphans'], false);
            }
        }
    }

    private static function ensure_menu(array $ids): void {
        $locs = get_nav_menu_locations();
        if (!empty($locs['primary'])) return; // already set

        $menu_id = wp_create_nav_menu('Primary');
        if (is_wp_error($menu_id)) return;

        $wanted = ['orphans','donor-dashboard','donor-receipts','verify-receipt'];
        foreach ($wanted as $slug) {
            if (!empty($ids[$slug])) {
                wp_update_nav_menu_item($menu_id, 0, [
                    'menu-item-object-id' => (int)$ids[$slug],
                    'menu-item-object'    => 'page',
                    'menu-item-type'      => 'post_type',
                    'menu-item-status'    => 'publish',
                ]);
            }
        }
        $locs['primary'] = $menu_id;
        set_theme_mod('nav_menu_locations', $locs);
    }
}
