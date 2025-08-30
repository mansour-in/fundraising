<?php
namespace FA\Fundraising\Shortcodes;

if (!defined('ABSPATH')) exit;

class VerifyShortcode {
    public function register(): void {
        add_shortcode('fa_verify_receipt', [$this,'render']);
    }

    public function render($atts = []) {
        ob_start(); ?>
        <div class="fa-card" style="border:1px solid #e5e7eb;border-radius:12px;padding:16px;background:#fff;max-width:640px;">
            <h3 style="margin-top:0;"><?php esc_html_e('Verify Receipt','fa-fundraising'); ?></h3>
            <p class="fa-muted" style="opacity:.8;"><?php esc_html_e('Enter your Razorpay Payment ID or Receipt Number to verify.','fa-fundraising'); ?></p>
            <form id="fa-vf" style="display:grid;gap:10px;">
                <input type="text" id="vf-input" placeholder="<?php esc_attr_e('Payment ID (pay_...) or Receipt No (YYYY-YY/000123)','fa-fundraising'); ?>" style="padding:.6rem;border:1px solid #cbd5e1;border-radius:8px;">
                <button class="fa-btn" type="submit" style="padding:.6rem 1rem;border-radius:8px;border:1px solid #111;background:#111;color:#fff;"><?php esc_html_e('Verify','fa-fundraising'); ?></button>
            </form>
            <div id="vf-out" style="margin-top:12px;"></div>
        </div>
        <script>
        (function(){
          const form = document.getElementById('fa-vf');
          const out  = document.getElementById('vf-out');
          const api  = (p)=> (window.wpApiSettings?.root || '/wp-json/') + 'faf/v1' + p;

          form.addEventListener('submit', async (e)=>{
            e.preventDefault();
            out.textContent = '<?php echo esc_js(__('Checking…','fa-fundraising')); ?>';
            const v = document.getElementById('vf-input').value.trim();
            if (!v) { out.textContent=''; return; }

            const q = new URLSearchParams();
            if (v.startsWith('pay_')) q.append('payment_id', v);
            else q.append('receipt_no', v);

            try{
              const r = await fetch(api('/verify/receipt?'+q.toString()));
              const j = await r.json();
              if (!j.ok) throw new Error(j.message || 'Not found');

              const a = new Intl.NumberFormat(undefined,{style:'currency',currency:(j.receipt.currency||'INR'),maximumFractionDigits:0}).format(j.receipt.amount||0);
              const date = new Date((j.receipt.date||'')+'Z').toLocaleString();
              out.innerHTML = `
                <div class="fa-card" style="border:1px dashed #e5e7eb;border-radius:10px;padding:10px;background:#fafafa;">
                  <div><strong><?php echo esc_js(__('Valid Receipt','fa-fundraising')); ?></strong></div>
                  <div><?php echo esc_js(__('Receipt No','fa-fundraising')); ?>: ${j.receipt.receipt_no || '—'}</div>
                  <div><?php echo esc_js(__('Donor','fa-fundraising')); ?>: ${j.receipt.donor_display}</div>
                  <div><?php echo esc_js(__('Amount','fa-fundraising')); ?>: ${a}</div>
                  <div><?php echo esc_js(__('Date','fa-fundraising')); ?>: ${date}</div>
                  <div class="fa-muted" style="margin-top:6px;opacity:.8;"><?php echo esc_js(get_bloginfo('name')); ?></div>
                </div>`;
            }catch(e){
              out.innerHTML = `<div style="color:#b91c1c;"><?php echo esc_js(__('No matching receipt found. Please check the ID/number.','fa-fundraising')); ?></div>`;
            }
          });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
