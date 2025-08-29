<?php
namespace FA\Fundraising\Shortcodes;

if (!defined('ABSPATH')) exit;

class DonorShortcodes {

    public function register(): void
    {
        add_shortcode('fa_donor_login', [$this, 'login']);
        add_shortcode('fa_donor_dashboard', [$this, 'dashboard']);
        add_shortcode('fa_receipts', [$this, 'receipts']);
        add_shortcode('fa_donor_settings', [$this, 'settings']);
    }

    public function login($atts = [], $content = ''): string
    {
        // Placeholder UI (Elementor widget will replace later)
        ob_start(); ?>
        <div class="fa-card">
            <h3><?php esc_html_e('Donor Login','fa-fundraising'); ?></h3>
            <p><?php esc_html_e('Email-based login coming in Step 2 (Magic Link / OTP).','fa-fundraising'); ?></p>
        </div>
        <?php return ob_get_clean();
    }

    public function dashboard($atts = [], $content = ''): string
    {
        if (!is_user_logged_in()) {
            return sprintf('<p>%s <a href="%s">%s</a></p>',
                esc_html__('Please log in to view your dashboard.','fa-fundraising'),
                esc_url(get_permalink((int) get_option('fa_donor_login_page_id'))),
                esc_html__('Login','fa-fundraising')
            );
        }
        ob_start(); ?>
        <div class="fa-card">
            <h3><?php esc_html_e('My Dashboard','fa-fundraising'); ?></h3>
            <p><?php esc_html_e('KPI cards, charts, and tables will appear here in later steps.','fa-fundraising'); ?></p>
        </div>
        <?php return ob_get_clean();
    }

    public function receipts($atts = [], $content = ''): string
    {
        if (!is_user_logged_in()) {
            return sprintf('<p>%s <a href="%s">%s</a></p>',
                esc_html__('Please log in to view your receipts.','fa-fundraising'),
                esc_url(get_permalink((int) get_option('fa_donor_login_page_id'))),
                esc_html__('Login','fa-fundraising')
            );
        }
        ob_start(); ?>
        <div class="fa-card">
            <h3><?php esc_html_e('My Receipts','fa-fundraising'); ?></h3>
            <p><?php esc_html_e('Receipt list and downloads will appear here.','fa-fundraising'); ?></p>
        </div>
        <?php return ob_get_clean();
    }

    public function settings($atts = [], $content = ''): string
    {
        if (!is_user_logged_in()) {
            return sprintf('<p>%s <a href="%s">%s</a></p>',
                esc_html__('Please log in to edit your settings.','fa-fundraising'),
                esc_url(get_permalink((int) get_option('fa_donor_login_page_id'))),
                esc_html__('Login','fa-fundraising')
            );
        }
        ob_start(); ?>
        <div class="fa-card">
            <h3><?php esc_html_e('My Settings','fa-fundraising'); ?></h3>
            <p><?php esc_html_e('Profile, PAN, address, privacy tools coming soon.','fa-fundraising'); ?></p>
        </div>
        <?php return ob_get_clean();
    }
}
