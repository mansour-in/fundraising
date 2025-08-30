<?php if (!defined('ABSPATH')) exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
// If Elementor Theme Builder header exists, render it.
if (function_exists('elementor_theme_do_location') && elementor_theme_do_location('header')) : ?>
<?php else: ?>
<header class="site-header">
  <div class="container" style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
    <a class="brand" href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a>
    <?php
      if (has_nav_menu('primary')) {
          wp_nav_menu([
              'theme_location' => 'primary',
              'container' => false,
              'menu_class' => 'nav',
              'fallback_cb' => 'fa_minimal_menu_fallback'
          ]);
      } else {
          fa_minimal_menu_fallback();
      }
    ?>
  </div>
</header>
<?php endif; ?>
<main class="main container">
