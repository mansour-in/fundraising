<?php
namespace FA\Fundraising\Widgets\Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) exit;

class DonationCTA extends Widget_Base {
    public function get_name(){ return 'fa_donation_cta'; }
    public function get_title(){ return __('FA Donation CTA','fa-fundraising'); }
    public function get_icon(){ return 'eicon-button'; }
    public function get_categories(){ return ['general']; }

    protected function register_controls() {
        $this->start_controls_section('cfg', ['label'=>__('Settings','fa-fundraising')]);
        $this->add_control('label', ['label'=>__('Button Label','fa-fundraising'),'type'=>Controls_Manager::TEXT,'default'=>__('Donate Now','fa-fundraising')]);
        $this->add_control('type', ['label'=>__('Type','fa-fundraising'),'type'=>Controls_Manager::SELECT,
            'options'=>['general'=>'General','cause'=>'Cause','sponsorship'=>'Sponsorship'], 'default'=>'general']);
        $this->add_control('cause_id', ['label'=>__('Cause ID (for type=cause)','fa-fundraising'),'type'=>Controls_Manager::NUMBER,'default'=>0]);
        $this->add_control('orphan_id', ['label'=>__('Orphan ID (for type=sponsorship)','fa-fundraising'),'type'=>Controls_Manager::NUMBER,'default'=>0]);
        $this->add_control('amount', ['label'=>__('Default Amount (INR)','fa-fundraising'),'type'=>Controls_Manager::NUMBER,'default'=>500]);
        $this->end_controls_section();
    }

    protected function render(){
        wp_enqueue_script('razorpay-checkout','https://checkout.razorpay.com/v1/checkout.js',[],null,true);
        $label = esc_html($this->get_settings('label'));
        $type  = esc_attr($this->get_settings('type'));
        $cause = (int)$this->get_settings('cause_id');
        $orph  = (int)$this->get_settings('orphan_id');
        $amt   = (float)$this->get_settings('amount');

        $root = esc_js( (function_exists('rest_url') ? rest_url('faf/v1') : site_url('/wp-json/faf/v1')) );
        ?>
        <button class="fa-cta" data-root="<?php echo $root; ?>" data-type="<?php echo $type; ?>" data-cause="<?php echo $cause; ?>" data-orphan="<?php echo $orph; ?>" data-amount="<?php echo $amt; ?>" style="padding:.8rem 1.2rem;border-radius:10px;border:1px solid #111;background:#111;color:#fff;">
            <?php echo $label; ?>
        </button>
        <script>
        (function(){
          const btn = document.currentScript.previousElementSibling;
          btn.addEventListener('click', async ()=>{
            const root = btn.getAttribute('data-root');
            const type = btn.getAttribute('data-type');
            const cause_id = parseInt(btn.getAttribute('data-cause'))||null;
            const orphan_id = parseInt(btn.getAttribute('data-orphan'))||null;
            const defAmt = parseFloat(btn.getAttribute('data-amount'))||0;

            const amount = prompt('<?php echo esc_js(__('Enter amount (INR)','fa-fundraising')); ?>', Math.round(defAmt||0)) || '';
            if (!amount || isNaN(parseFloat(amount))) return;

            const email = prompt('<?php echo esc_js(__('Your email','fa-fundraising')); ?>')||'';
            if (!email) return;
            const name = prompt('<?php echo esc_js(__('Your name (optional)','fa-fundraising')); ?>')||'';
            const phone = prompt('<?php echo esc_js(__('Phone (optional)','fa-fundraising')); ?>')||'';

            const r = await fetch(root + '/checkout/order', {
              method:'POST', headers:{'Content-Type':'application/json'},
              body: JSON.stringify({ amount: parseFloat(amount), currency:'INR', type, cause_id, orphan_id, email, name, phone })
            });
            const j = await r.json();
            if (!j.ok) { alert(j.message || 'Error'); return; }

            const options = {
              key: j.key_id,
              order_id: j.order.id,
              name: document.title || 'Future Achievers',
              prefill: { email, name, contact: phone },
              handler: async function(resp){
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
        })();
        </script>
        <?php
    }
}
