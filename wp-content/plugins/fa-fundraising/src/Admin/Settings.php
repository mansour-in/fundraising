<?php
namespace FA\Fundraising\Admin;

if (!defined('ABSPATH')) exit;

class Settings {
    public function init(): void {
        add_action('admin_menu', function(){
            add_menu_page(
                __('FA Fundraising','fa-fundraising'),
                'FA Fundraising',
                'manage_options',
                'fa-fundraising',
                [$this,'render'],
                'dashicons-heart',
                58
            );
        });
        add_action('admin_init', function(){
            register_setting('fa_fundraising', 'fa_rzp_key_id');
            register_setting('fa_fundraising', 'fa_rzp_key_secret');
            register_setting('fa_fundraising', 'fa_rzp_webhook_secret');
            register_setting('fa_fundraising', 'fa_org_address'); // optional for PDFs
            register_setting('fa_fundraising', 'fa_org_logo_url');
            register_setting('fa_fundraising', 'fa_org_pan');
            register_setting('fa_fundraising', 'fa_org_80g_reg');
        });
    }

    public function render(): void {
        ?>
        <div class="wrap">
          <h1><?php esc_html_e('Future Achievers Fundraising - Settings','fa-fundraising'); ?></h1>
          <form method="post" action="options.php">
            <?php settings_fields('fa_fundraising'); ?>
            <table class="form-table" role="presentation">
              <tr><th><label>Razorpay Key ID</label></th><td><input type="text" name="fa_rzp_key_id" value="<?php echo esc_attr(get_option('fa_rzp_key_id','')); ?>" class="regular-text"></td></tr>
              <tr><th><label>Razorpay Key Secret</label></th><td><input type="password" name="fa_rzp_key_secret" value="<?php echo esc_attr(get_option('fa_rzp_key_secret','')); ?>" class="regular-text"></td></tr>
              <tr><th><label>Webhook Secret</label></th><td><input type="text" name="fa_rzp_webhook_secret" value="<?php echo esc_attr(get_option('fa_rzp_webhook_secret','')); ?>" class="regular-text"></td></tr>
            </table>
            <h2 class="title">Receipt/Org Details (optional for PDFs)</h2>
            <table class="form-table" role="presentation">
              <tr><th><label>Org Address</label></th><td><textarea name="fa_org_address" class="large-text" rows="3"><?php echo esc_textarea(get_option('fa_org_address','')); ?></textarea></td></tr>
              <tr><th><label>Org Logo URL</label></th><td><input type="text" name="fa_org_logo_url" value="<?php echo esc_attr(get_option('fa_org_logo_url','')); ?>" class="regular-text"></td></tr>
              <tr><th><label>Org PAN</label></th><td><input type="text" name="fa_org_pan" value="<?php echo esc_attr(get_option('fa_org_pan','')); ?>" class="regular-text"></td></tr>
              <tr><th><label>80G Registration No.</label></th><td><input type="text" name="fa_org_80g_reg" value="<?php echo esc_attr(get_option('fa_org_80g_reg','')); ?>" class="regular-text"></td></tr>
            </table>
            <?php submit_button(); ?>
          </form>
          <p><strong>Webhook URL:</strong> <?php echo esc_url( rest_url('faf/v1/webhook/razorpay') ); ?></p>
        </div>
        <?php
    }
}
