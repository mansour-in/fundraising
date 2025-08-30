<?php
/* Single template for FA Cause */
get_header();
the_post();
$cid = get_the_ID();
?>
<section class="fa-hero">
  <h1><?php the_title(); ?></h1>
  <?php if (has_post_thumbnail()) the_post_thumbnail('large', ['style'=>'border-radius:12px;max-width:100%;height:auto;']); ?>
</section>

<div class="content"><?php the_content(); ?></div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-top:16px;">
  <div>
    <?php echo do_shortcode('[fa_recent_donors type="cause" cause_id="'.$cid.'" limit="8"]'); ?>
  </div>
  <aside>
    <?php
      // Progress bar + Donation CTA
      if (class_exists('\\FA\\Fundraising\\Widgets\\Elementor\\CauseProgress')) {
          echo do_shortcode('[fa_donation_cta type="cause" cause_id="'.$cid.'" amount="500"]');
      }
      // Fallback to shortcode if you created one, else keep CTA only:
      // echo do_shortcode('[fa_cause_progress cause_id="'.$cid.'"]');
    ?>
  </aside>
</div>
<?php get_footer(); ?>
