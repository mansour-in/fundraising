<?php
/* Template Name: Donor Receipts */
get_header(); the_post(); ?>
<h1><?php the_title(); ?></h1>
<?php echo do_shortcode('[fa_receipts]'); ?>
<?php get_footer(); ?>
