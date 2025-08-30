<?php
/*
Plugin Name: Future Achievers Fundraising
Description: Donations, subscriptions, receipts (80G) for NGOs. Elementor-friendly.
Version: 1.0.0
Author: Mansour M
Text Domain: fa-fundraising
*/

if (!defined('ABSPATH')) exit;

define('FAF_VERSION', '1.0.0');
define('FAF_FILE', __FILE__);
define('FAF_PATH', plugin_dir_path(__FILE__));
define('FAF_URL', plugin_dir_url(__FILE__));

/** Composer autoload (recommended) */
$autoload = FAF_PATH . 'vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

/** Fallback simple autoloader (if composer not used yet) */
spl_autoload_register(function($class){
    if (strpos($class, 'FA\\Fundraising\\') !== 0) return;
    $path = FAF_PATH . 'src/' . str_replace(['FA\\Fundraising\\','\\'], ['','/'], $class) . '.php';
    if (file_exists($path)) require_once $path;
});

use FA\Fundraising\Bootstrap;
use FA\Fundraising\Setup\Activator;

register_activation_hook(__FILE__, [Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [Activator::class, 'deactivate']);
register_uninstall_hook(__FILE__, ['FA\\Fundraising\\Setup\\Activator', 'uninstall']);

add_action('plugins_loaded', function(){
    (new Bootstrap())->init();
});
