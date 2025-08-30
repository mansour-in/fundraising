<?php
/* Template Name: Donor Login */
get_header(); the_post(); ?>
<h1><?php the_title(); ?></h1>
<?php
$redirect = get_permalink( (int) get_option('fa_donor_dashboard_page_id') );
wp_login_form(['redirect' => $redirect ?: home_url('/')]);
?>
<?php get_footer(); ?>
