<?php
namespace FA\Fundraising\Setup;

use wpdb;

if (!defined('ABSPATH')) exit;

class Activator {

    public static function activate(): void
    {
        self::check_requirements();
        self::create_tables();
        self::add_roles_caps();
        self::create_frontend_pages();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    public static function uninstall(): void
    {
        // Data retention is usually required for NGO finance — do NOT drop tables.
        // If you ever need to, guard with confirmations and options flags.
    }

    private static function check_requirements(): void
    {
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            deactivate_plugins(plugin_basename(FAF_FILE));
            wp_die('Future Achievers Fundraising requires PHP 8.1+');
        }
        global $wp_version;
        if (version_compare($wp_version, '6.5', '<')) {
            deactivate_plugins(plugin_basename(FAF_FILE));
            wp_die('Future Achievers Fundraising requires WordPress 6.5+');
        }
    }

    private static function create_tables(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        $donations = $wpdb->prefix . 'fa_donations';
        $sponsorships = $wpdb->prefix . 'fa_sponsorships';
        $subscriptions = $wpdb->prefix . 'fa_subscriptions';

        $sql1 = "CREATE TABLE {$donations} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            user_id BIGINT UNSIGNED NULL,
            donor_name VARCHAR(191) NULL,
            donor_email VARCHAR(191) NULL,
            phone VARCHAR(32) NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            currency VARCHAR(10) NOT NULL DEFAULT 'INR',
            type VARCHAR(20) NOT NULL DEFAULT 'general',
            orphan_id BIGINT UNSIGNED NULL,
            cause_id BIGINT UNSIGNED NULL,
            razorpay_payment_id VARCHAR(64) NULL,
            razorpay_order_id VARCHAR(64) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            financial_year VARCHAR(16) NULL,
            receipt_no VARCHAR(32) NULL,
            receipt_pdf VARCHAR(255) NULL,
            receipt80g_pdf VARCHAR(255) NULL,
            verify_hash VARCHAR(64) NULL,
            meta LONGTEXT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_payment_id (razorpay_payment_id),
            KEY idx_order_id (razorpay_order_id),
            KEY idx_status (status),
            KEY idx_user (user_id),
            KEY idx_orphan (orphan_id),
            KEY idx_cause (cause_id),
            KEY idx_fy_receipt (financial_year, receipt_no)
        ) $charset;";

        $sql2 = "CREATE TABLE {$sponsorships} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            orphan_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            donor_email VARCHAR(191) NULL,
            donor_name VARCHAR(191) NULL,
            phone VARCHAR(32) NULL,
            slot_no INT UNSIGNED NULL,
            periodicity VARCHAR(12) NOT NULL DEFAULT 'monthly',
            amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            start_date DATE NULL,
            next_charge_on DATE NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            meta LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY idx_orphan (orphan_id),
            KEY idx_user (user_id),
            KEY idx_status (status)
        ) $charset;";

        $sql3 = "CREATE TABLE {$subscriptions} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            razorpay_subscription_id VARCHAR(64) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            donor_email VARCHAR(191) NULL,
            plan_id VARCHAR(64) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            current_start DATETIME NULL,
            current_end DATETIME NULL,
            notes LONGTEXT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_sub (razorpay_subscription_id),
            KEY idx_user (user_id),
            KEY idx_status (status)
        ) $charset;";

        $auth = $wpdb->prefix . 'fa_auth_tokens';
        $sql4 = "CREATE TABLE {$auth} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            email VARCHAR(191) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            type VARCHAR(10) NOT NULL, /* magic|otp */
            secret VARCHAR(255) NOT NULL, /* hashed token or OTP */
            expires_at DATETIME NOT NULL,
            used TINYINT(1) NOT NULL DEFAULT 0,
            meta LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY idx_email (email),
            KEY idx_user (user_id),
            KEY idx_type (type),
            KEY idx_used_exp (used, expires_at)
        ) $charset;";

        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);
    }

    private static function add_roles_caps(): void
    {
        // Donor (front-end only)
        add_role('fa_donor', __('FA Donor','fa-fundraising'), ['read' => true]);

        // Managers (wp-admin)
        $manager_caps = [
            'read' => true,
            'list_users' => true,
            'edit_users' => true,
            'manage_options' => true
        ];
        add_role('fa_manager', __('FA Manager','fa-fundraising'), $manager_caps);

        // Accountant (limited)
        $acct_caps = [
            'read' => true,
            'list_users' => true
        ];
        add_role('fa_accountant', __('FA Accountant','fa-fundraising'), $acct_caps);
    }

    private static function create_frontend_pages(): void
    {
        $pages = [
            'fa_donor_login_page_id'     => ['title' => 'Donor Login',     'slug' => 'donor-login',     'shortcode' => '[fa_donor_login]'],
            'fa_donor_dashboard_page_id' => ['title' => 'Donor Dashboard', 'slug' => 'donor-dashboard', 'shortcode' => '[fa_donor_dashboard]'],
            'fa_donor_receipts_page_id'  => ['title' => 'My Receipts',     'slug' => 'donor-receipts',  'shortcode' => '[fa_receipts]'],
            'fa_donor_settings_page_id'  => ['title' => 'My Settings',     'slug' => 'donor-settings',  'shortcode' => '[fa_donor_settings]'],
        ];

        foreach ($pages as $option_key => $data) {
            $existing = get_option($option_key);
            if ($existing && get_post_status((int)$existing)) continue;

            $pid = wp_insert_post([
                'post_title'   => wp_strip_all_tags($data['title']),
                'post_name'    => sanitize_title($data['slug']),
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => $data['shortcode']
            ]);
            if ($pid && !is_wp_error($pid)) {
                update_option($option_key, (int)$pid);
            }
        }
    }
}
