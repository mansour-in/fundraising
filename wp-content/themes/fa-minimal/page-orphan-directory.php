<?php
/* Template Name: Orphan Directory */
get_header(); the_post(); ?>
<h1><?php the_title(); ?></h1>
<p><?php esc_html_e('Sponsor a child — monthly or one-time.','fa-minimal'); ?></p>
<?php echo do_shortcode('[fa_orphan_grid per_page="12"]'); ?>
<?php get_footer(); ?>
