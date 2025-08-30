<?php get_header(); ?>
<?php if (have_posts()): while (have_posts()): the_post(); ?>
  <article <?php post_class('entry'); ?>>
    <h1><?php the_title(); ?></h1>
    <div class="content"><?php the_content(); ?></div>
  </article>
<?php endwhile; else: ?>
  <p><?php esc_html_e('Nothing found.','fa-minimal'); ?></p>
<?php endif; ?>
<?php get_footer(); ?>
