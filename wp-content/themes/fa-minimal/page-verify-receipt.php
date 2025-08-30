<?php
/* Template Name: Verify Receipt */
get_header(); the_post(); ?>
<h1><?php the_title(); ?></h1>
<?php echo do_shortcode('[fa_verify_receipt]'); ?>
<?php get_footer(); ?>
