<?php
/* Template Name: Donor Login */
get_header(); the_post(); ?>
<h1><?php the_title(); ?></h1>
<?php
echo do_shortcode('[fa_donor_login]');
?>
<?php get_footer(); ?>
