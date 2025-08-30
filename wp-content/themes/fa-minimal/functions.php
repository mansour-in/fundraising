<?php
if (!defined('ABSPATH')) exit;

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form','gallery','caption','style','script']);
    register_nav_menus(['primary' => __('Primary Menu','fa-minimal')]);
    add_theme_support('elementor-location-header');
    add_theme_support('elementor-location-footer');
});

// Light perf tweaks
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('fa-minimal', get_stylesheet_uri(), [], '1.0.0');
    add_action('wp_head', function () {
        echo '<link rel="dns-prefetch" href="//checkout.razorpay.com">' . PHP_EOL;
        echo '<link rel="preconnect" href="https://checkout.razorpay.com" crossorigin>' . PHP_EOL;
        echo '<link rel="dns-prefetch" href="//cdn.jsdelivr.net">' . PHP_EOL;
        echo '<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>' . PHP_EOL;
    }, 1);
}, 9);

// Trim head noise (optional)
add_action('init', function () {
    remove_action('wp_head','print_emoji_detection_script',7);
    remove_action('wp_print_styles','print_emoji_styles');
    remove_action('wp_head','wp_oembed_add_discovery_links');
    remove_action('wp_head','wp_oembed_add_host_js');
}, 20);

// One-click installer
require_once get_template_directory() . '/inc/Installer.php';
add_action('after_switch_theme', ['FA_Minimal_Installer','run']); // fire on activation

// Optional: WP-CLI rerun
if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('fa oneclick', ['FA_Minimal_Installer','run_cli']);
}
