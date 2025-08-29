<?php
namespace FA\Fundraising;

if (!defined('ABSPATH')) exit;

class Bootstrap {
    public function init(): void
    {
        // i18n
        add_action('init', function(){
            load_plugin_textdomain('fa-fundraising', false, dirname(plugin_basename(FAF_FILE)).'/languages');
        });

        // Shortcodes
        (new \FA\Fundraising\Shortcodes\DonorShortcodes())->register();
        (new \FA\Fundraising\Shortcodes\FundraisingShortcodes())->register();

        (new \FA\Fundraising\Auth\Routes())->init();

        (new \FA\Fundraising\Api\StatsController())->init();
        (new \FA\Fundraising\Api\DonationController())->init();
        (new \FA\Fundraising\Api\CheckoutController())->init();
        (new \FA\Fundraising\Api\DirectoryController())->init();
        (new \FA\Fundraising\Admin\Settings())->init();
        (new \FA\Fundraising\Payments\WebhookController())->init();
        (new \FA\Fundraising\CPT\Taxonomies())->init();
        (new \FA\Fundraising\CPT\Orphan())->init();
        (new \FA\Fundraising\CPT\Cause())->init();

        (new \FA\Fundraising\Widgets\Elementor\Plugin())->init();

        // Minimal REST namespace (we’ll add routes later)
        add_action('rest_api_init', function(){
            register_rest_route('faf/v1', '/ping', [
                'methods'  => 'GET',
                'callback' => fn() => ['ok' => true, 'version' => FAF_VERSION],
                'permission_callback' => '__return_true'
            ]);
        });
    }
}
