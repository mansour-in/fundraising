<?php
namespace FA\Fundraising\Widgets\Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) exit;

class OrphanGrid extends Widget_Base {
    public function get_name(){ return 'fa_orphan_grid'; }
    public function get_title(){ return __('FA Orphan Grid','fa-fundraising'); }
    public function get_icon(){ return 'eicon-posts-grid'; }
    public function get_categories(){ return ['general']; }

    protected function register_controls() {
        $this->start_controls_section('content', ['label'=>__('Content','fa-fundraising')]);
        $this->add_control('district', [
            'label'=>__('Filter by District (name)','fa-fundraising'),
            'type'=>Controls_Manager::TEXT, 'default'=>''
        ]);
        $this->add_control('status', [
            'label'=>__('Status','fa-fundraising'),
            'type'=>Controls_Manager::SELECT,
            'options'=>[''=>'All','unsponsored'=>'Unsponsored','partial'=>'Partial','full'=>'Full'],
            'default'=>''
        ]);
        $this->add_control('per_page', [
            'label'=>__('Per Page','fa-fundraising'),
            'type'=>Controls_Manager::NUMBER, 'default'=>12
        ]);
        $this->end_controls_section();
    }

    protected function render(){
        wp_enqueue_script('razorpay-checkout','https://checkout.razorpay.com/v1/checkout.js',[],null,true);

        $district = esc_attr($this->get_settings('district') ?: '');
        $status   = esc_attr($this->get_settings('status') ?: '');
        $per      = (int)($this->get_settings('per_page') ?: 12);

        $root = esc_js( (function_exists('rest_url') ? rest_url('faf/v1') : site_url('/wp-json/faf/v1')) );
        ?>
        <div class="fa-orphan-grid" data-root="<?php echo $root; ?>" data-district="<?php echo $district; ?>" data-status="<?php echo $status; ?>" data-per="<?php echo $per; ?>" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;">
            <div class="fa-grid-loading" style="grid-column:1/-1;opacity:.7;"><?php esc_html_e('Loading...','fa-fundraising'); ?></div>
        </div>
        <script>
        (function(){
          const el = document.currentScript.previousElementSibling;
          const root = el.getAttribute('data-root');
          const district = el.getAttribute('data-district')||'';
          const status = el.getAttribute('data-status')||'';
          const per = el.getAttribute('data-per')||'12';
          const q = new URLSearchParams({per_page: per});
          if (district) q.append('district', district);
          if (status) q.append('status', status);

          fetch(root + '/orphans?' + q.toString())
            .then(r=>r.json()).then(j=>{
              el.innerHTML='';
              if (!j.ok || !j.items?.length) {
                el.innerHTML = '<div style="grid-column:1/-1;"><?php echo esc_js(__('No orphans found.','fa-fundraising')); ?></div>';
                return;
              }
              j.items.forEach(o=>{
                const card = document.createElement('div');
                card.className='fa-card';
                card.style='border:1px solid #e5e7eb;border-radius:12px;padding:12px;background:#fff;display:flex;flex-direction:column;gap:8px;';
                card.innerHTML = `
                  <img src="${o.image||''}" alt="" style="width:100%;height:140px;object-fit:cover;border-radius:8px;">
                  <div><strong>${o.name}</strong><br><span style="opacity:.7;"><?php echo esc_js(__('Age','fa-fundraising')); ?>: ${o.age||'—'} | ${o.districts?.[0]||''}</span></div>
                  <div style="font-size:.95rem;"><?php echo esc_js(__('Monthly Cost','fa-fundraising')); ?>: ₹${Math.round(o.monthly_cost||0)}</div>
                  <div style="opacity:.8;"><?php echo esc_js(__('Slots','fa-fundraising')); ?>: ${o.slots_filled}/${o.slots_total} • ${o.status}</div>
                  <div style="display:flex;gap:6px;margin-top:6px;">
                    <button class="fa-donate" data-type="sponsorship" data-orphan="${o.id}" data-amount="${o.monthly_cost||0}" style="flex:1;padding:.6rem 1rem;border-radius:8px;border:1px solid #111;background:#111;color:#fff;"><?php echo esc_js(__('Sponsor Now','fa-fundraising')); ?></button>
                    <button class="fa-donate" data-type="general" data-amount="${o.monthly_cost||0}" style="padding:.6rem .9rem;border-radius:8px;border:1px solid #e5e7eb;background:#f9fafb;"><?php echo esc_js(__('Donate','fa-fundraising')); ?></button>
                  </div>
                `;
                el.appendChild(card);
              });

              el.addEventListener('click', async (e)=>{
                const b = e.target.closest('.fa-donate'); if (!b) return;
                const type = b.getAttribute('data-type');
                const orphan_id = b.getAttribute('data-orphan') || null;
                const amount = prompt('<?php echo esc_js(__('Enter amount (INR)','fa-fundraising')); ?>', Math.round(b.getAttribute('data-amount')||0)) || '';
                if (!amount || isNaN(parseFloat(amount))) return;

                const email = prompt('<?php echo esc_js(__('Your email','fa-fundraising')); ?>')||'';
                if (!email) return;
                const name = prompt('<?php echo esc_js(__('Your name (optional)','fa-fundraising')); ?>')||'';
                const phone = prompt('<?php echo esc_js(__('Phone (optional)','fa-fundraising')); ?>')||'';

                const r = await fetch(root + '/checkout/order', {
                  method:'POST', headers:{'Content-Type':'application/json'},
                  body: JSON.stringify({ amount: parseFloat(amount), currency:'INR', type, orphan_id, email, name, phone })
                });
                const j = await r.json();
                if (!j.ok) { alert(j.message || 'Error'); return; }

                const options = {
                  key: j.key_id,
                  order_id: j.order.id,
                  name: document.title || 'Future Achievers',
                  prefill: { email: email, name: name, contact: phone },
                  handler: async function (resp) {
                    try{
                      await fetch((window.wpApiSettings?.root || '/wp-json/')+'faf/v1/checkout/verify', {
                        method:'POST', headers:{'Content-Type':'application/json'},
                        body: JSON.stringify({
                          order_id: j.order.id,
                          payment_id: resp.razorpay_payment_id,
                          signature: resp.razorpay_signature,
                          notes: j.order.notes || {}
                        })
                      });
                    }catch(e){}
                    window.location.href = '<?php echo esc_url(get_permalink( (int) get_option('fa_donor_receipts_page_id') )); ?>';
                  }
                };
                const rz = new window.Razorpay(options);
                rz.open();
              });
            })
        })();
        </script>
        <?php
    }
}
