<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form','gallery','caption','style','script']);
    register_nav_menus(['primary' => __('Primary Menu','fa-minimal')]);
    // Elementor friendliness
    add_theme_support('elementor-location-header');
    add_theme_support('elementor-location-footer');
});

// Enqueue minimal CSS and small performance tweaks
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('fa-minimal', get_stylesheet_uri(), [], '1.0.0');

    // DNS Prefetch / Preconnect for Razorpay & CDN
    add_action('wp_head', function () {
        echo '<link rel="dns-prefetch" href="//checkout.razorpay.com">'.PHP_EOL;
        echo '<link rel="preconnect" href="https://checkout.razorpay.com" crossorigin>'.PHP_EOL;
        echo '<link rel="dns-prefetch" href="//cdn.jsdelivr.net">'.PHP_EOL;
        echo '<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>'.PHP_EOL;
    }, 1);

    // Dequeue some defaults for speed (safe in most installs)
    wp_deregister_style('wp-block-library'); // Gutenberg CSS
    wp_deregister_style('global-styles');    // theme.json
}, 9);

// Trim head noise (emojis, oEmbed)
add_action('init', function () {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'wp_oembed_add_host_js');
}, 20);

// Place jQuery in footer (front-end) and drop migrate if present
add_action('wp_enqueue_scripts', function () {
    if (!is_admin() && wp_script_is('jquery', 'registered')) {
        wp_deregister_script('jquery');
        wp_register_script('jquery', includes_url('/js/jquery/jquery.min.js'), [], '3.7.1', true);
        wp_enqueue_script('jquery');
        wp_deregister_script('jquery-migrate');
    }
}, 100);

// Basic Elementor container width (optional)
if (!defined('ELEMENTOR_DEFAULT_GENERIC_FONT')) {
    define('ELEMENTOR_DEFAULT_GENERIC_FONT', 'system-ui');
}

// Helper: menu fallback
function fa_minimal_menu_fallback() {
    echo '<nav class="nav"><a href="'.esc_url(home_url('/')).'">'.esc_html__('Home','fa-minimal').'</a></nav>';
}
