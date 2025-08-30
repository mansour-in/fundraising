<?php if (!defined('ABSPATH')) exit; ?>
</main>
<?php
if (function_exists('elementor_theme_do_location') && elementor_theme_do_location('footer')) : ?>
<?php else: ?>
<footer class="site-footer">
  <div class="container" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
    <div>&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?></div>
    <div>
      <a href="<?php echo esc_url(site_url('/verify-receipt')); ?>"><?php esc_html_e('Verify Receipt','fa-minimal'); ?></a>
    </div>
  </div>
</footer>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
