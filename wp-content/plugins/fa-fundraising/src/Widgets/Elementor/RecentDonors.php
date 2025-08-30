<?php
namespace FA\Fundraising\Widgets\Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) exit;

class RecentDonors extends Widget_Base {
    public function get_name(){ return 'fa_recent_donors'; }
    public function get_title(){ return __('FA Recent Donors','fa-fundraising'); }
    public function get_icon(){ return 'eicon-person'; }
    public function get_categories(){ return ['general']; }

    protected function register_controls() {
        $this->start_controls_section('cfg', ['label'=>__('Filters','fa-fundraising')]);
        $this->add_control('type', ['label'=>__('Type'),'type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'All','general'=>'General','cause'=>'Cause','sponsorship'=>'Sponsorship']]);
        $this->add_control('cause_id', ['label'=>__('Cause ID (optional)','fa-fundraising'),'type'=>Controls_Manager::NUMBER,'default'=>0]);
        $this->add_control('orphan_id', ['label'=>__('Orphan ID (optional)','fa-fundraising'),'type'=>Controls_Manager::NUMBER,'default'=>0]);
        $this->add_control('limit', ['label'=>__('Count'),'type'=>Controls_Manager::NUMBER,'default'=>10]);
        $this->end_controls_section();
    }

    protected function render() {
        $root = esc_js( rest_url('faf/v1') );
        $type = esc_js($this->get_settings('type') ?: '');
        $cause= (int)$this->get_settings('cause_id');
        $orph = (int)$this->get_settings('orphan_id');
        $lim  = (int)($this->get_settings('limit') ?: 10);
        ?>
        <div class="fa-card" data-root="<?php echo $root; ?>" data-type="<?php echo $type; ?>" data-cause="<?php echo $cause; ?>" data-orph="<?php echo $orph; ?>" data-limit="<?php echo $lim; ?>" style="border:1px solid #e5e7eb;border-radius:12px;padding:14px;background:#fff;">
            <h4 style="margin-top:0;"><?php esc_html_e('Recent Donors','fa-fundraising'); ?></h4>
            <ul id="fa-rd" style="padding-left:1rem;margin:.5rem 0 0;">
                <li class="fa-muted"><?php esc_html_e('Loading…','fa-fundraising'); ?></li>
            </ul>
        </div>
        <script>
        (function(){
          const el = document.currentScript.previousElementSibling;
          const root  = el.getAttribute('data-root');
          const type  = el.getAttribute('data-type')||'';
          const cause = el.getAttribute('data-cause')||'0';
          const orph  = el.getAttribute('data-orph')||'0';
          const limit = el.getAttribute('data-limit')||'10';
          const q = new URLSearchParams({limit});
          if (type) q.append('type', type);
          if (parseInt(cause)) q.append('cause_id', cause);
          if (parseInt(orph))  q.append('orphan_id', orph);

          fetch(root + '/public/recent-donations?' + q.toString())
            .then(r=>r.json()).then(j=>{
              const ul = el.querySelector('#fa-rd'); ul.innerHTML='';
              if (!j.ok || !j.items?.length){
                ul.innerHTML = '<li class="fa-muted"><?php echo esc_js(__('No donations yet.','fa-fundraising')); ?></li>';
                return;
              }
              j.items.forEach(x=>{
                const li = document.createElement('li');
                const d = new Date(x.date+'Z').toLocaleDateString();
                const amt = new Intl.NumberFormat(undefined,{style:'currency',currency:(x.currency||'INR'),maximumFractionDigits:0}).format(x.amount||0);
                li.textContent = `${x.donor} — ${amt} (${d})`;
                ul.appendChild(li);
              });
            });
        })();
        </script>
        <?php
    }
}
