<?php
namespace FA\Fundraising\Shortcodes;

if (!defined('ABSPATH')) exit;

class FundraisingShortcodes {
    public function register(): void {
        add_shortcode('fa_orphan_grid', [$this,'grid']);
    }

    public function grid($atts){
        $atts = shortcode_atts(['district'=>'','status'=>'','per_page'=>12], $atts);
        ob_start();
        wp_enqueue_script('razorpay-checkout','https://checkout.razorpay.com/v1/checkout.js',[],null,true);
        $root = esc_js( (function_exists('rest_url') ? rest_url('faf/v1') : site_url('/wp-json/faf/v1')) );
        ?>
        <div class="fa-orphan-grid" data-root="<?php echo $root; ?>" data-district="<?php echo esc_attr($atts['district']); ?>" data-status="<?php echo esc_attr($atts['status']); ?>" data-per="<?php echo (int)$atts['per_page']; ?>" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;">
          <div class="fa-grid-loading" style="grid-column:1/-1;opacity:.7;"><?php esc_html_e('Loading...','fa-fundraising'); ?></div>
        </div>
        <script>
        window.fa_donor_receipts_url = '<?php echo esc_js(get_permalink( (int) get_option('fa_donor_receipts_page_id') )); ?>';
        (<?php echo file_get_contents(__DIR__.'/../Widgets/Elementor/_orphan_grid_boot.js'); ?>)();
        </script>
        <?php
        return ob_get_clean();
    }
}
