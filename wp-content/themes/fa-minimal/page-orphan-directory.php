<?php
/* Template Name: Orphan Directory */
get_header(); the_post(); ?>
<section class="fa-hero">
  <h1><?php the_title(); ?></h1>
  <p class="fa-muted"><?php esc_html_e('Sponsor a child — monthly or one-time.','fa-minimal'); ?></p>
</section>

<?php
// Elementor widget or shortcode version — both work.
// If Elementor page, you can ignore this and build visually.
// Shortcode grid:
echo do_shortcode('[fa_orphan_grid per_page="12"]');
?>

<?php get_footer(); ?>
