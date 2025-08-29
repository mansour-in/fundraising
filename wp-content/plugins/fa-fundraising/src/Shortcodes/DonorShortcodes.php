<?php
namespace FA\Fundraising\Shortcodes;

if (!defined('ABSPATH')) exit;

class DonorShortcodes {

    public function register(): void
    {
        add_shortcode('fa_donor_login', [$this, 'login']);
        add_shortcode('fa_donor_dashboard', [$this, 'dashboard']);
        add_shortcode('fa_receipts', [$this, 'receipts']);
        add_shortcode('fa_donor_settings', [$this, 'settings']);
    }

    public function login($atts = [], $content = ''): string
    {
        // Handle Magic Link token login if present
        $res = \FA\Fundraising\Auth\MagicLink::try_consume_from_query();
        if ($res === 'OK') {
            $dash = get_permalink((int) get_option('fa_donor_dashboard_page_id'));
            // Redirect after cookie set
            wp_safe_redirect($dash);
            exit;
        } elseif (is_string($res) && $res !== null) {
            $notice = '<div class="fa-notice">'.esc_html($res).'</div>';
        } else {
            $notice = '';
        }

        if (is_user_logged_in()) {
            $dash = esc_url(get_permalink((int) get_option('fa_donor_dashboard_page_id')));
            return '<p>'.esc_html__('You are already logged in.','fa-fundraising').' <a href="'.$dash.'">'.esc_html__('Go to Dashboard','fa-fundraising').'</a></p>';
        }

        ob_start(); ?>
        <div class="fa-card">
            <h3><?php esc_html_e('Donor Login','fa-fundraising'); ?></h3>
            <?php echo $notice; ?>
            <div class="fa-login-forms" style="display:grid;gap:1rem;max-width:420px;">
                <form id="fa-magic-form">
                    <label><?php esc_html_e('Email','fa-fundraising'); ?></label>
                    <input type="email" name="email" required style="width:100%;padding:.6rem;">
                    <button type="submit" style="padding:.6rem 1rem;"><?php esc_html_e('Send Login Link','fa-fundraising'); ?></button>
                    <p id="fa-magic-msg" style="margin:.5rem 0 0;"></p>
                </form>

                <form id="fa-otp-form">
                    <label><?php esc_html_e('Email','fa-fundraising'); ?></label>
                    <input type="email" name="email" required style="width:100%;padding:.6rem;">
                    <button type="button" id="fa-otp-send" style="padding:.6rem 1rem;"><?php esc_html_e('Send OTP','fa-fundraising'); ?></button>
                    <div id="fa-otp-box" style="display:none;margin-top:.5rem;">
                        <label><?php esc_html_e('Enter OTP','fa-fundraising'); ?></label>
                        <input type="text" name="otp" pattern="[0-9]{6}" style="width:100%;padding:.6rem;">
                        <button type="submit" style="padding:.6rem 1rem;"><?php esc_html_e('Verify & Login','fa-fundraising'); ?></button>
                        <p id="fa-otp-msg" style="margin:.5rem 0 0;"></p>
                    </div>
                </form>
            </div>
        </div>
        <script>
        (function(){
            const api = (path)=> (window.wpApiSettings?.root || '/wp-json/') + 'faf/v1' + path;

            document.getElementById('fa-magic-form')?.addEventListener('submit', async (e)=>{
                e.preventDefault();
                const email = e.target.querySelector('input[name="email"]').value.trim();
                const msgEl = document.getElementById('fa-magic-msg');
                msgEl.textContent = '<?php echo esc_js(__('Sending...','fa-fundraising')); ?>';
                try{
                    const r = await fetch(api('/auth/request-link'), {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({email})});
                    const j = await r.json();
                    msgEl.textContent = j.ok ? '<?php echo esc_js(__('Check your email for the login link.','fa-fundraising')); ?>' : (j.message || 'Error');
                }catch(err){ msgEl.textContent = 'Error'; }
            });

            const otpForm = document.getElementById('fa-otp-form');
            const otpBox = document.getElementById('fa-otp-box');
            document.getElementById('fa-otp-send')?.addEventListener('click', async ()=>{
                const email = otpForm.querySelector('input[name="email"]').value.trim();
                const msgEl = document.getElementById('fa-otp-msg');
                msgEl.textContent = '<?php echo esc_js(__('Sending OTP...','fa-fundraising')); ?>';
                try{
                    const r = await fetch(api('/auth/request-otp'), {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({email})});
                    const j = await r.json();
                    if (j.ok) { otpBox.style.display = 'block'; msgEl.textContent = '<?php echo esc_js(__('OTP sent to your email.','fa-fundraising')); ?>'; }
                    else { msgEl.textContent = j.message || 'Error'; }
                }catch(err){ msgEl.textContent = 'Error'; }
            });

            otpForm?.addEventListener('submit', async (e)=>{
                e.preventDefault();
                const email = otpForm.querySelector('input[name="email"]').value.trim();
                const otp = otpForm.querySelector('input[name="otp"]').value.trim();
                const msgEl = document.getElementById('fa-otp-msg');
                msgEl.textContent = '<?php echo esc_js(__('Verifying...','fa-fundraising')); ?>';
                try{
                    const r = await fetch(api('/auth/verify-otp'), {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({email, otp})});
                    const j = await r.json();
                    if (j.ok) { window.location.href = '<?php echo esc_url(get_permalink((int) get_option('fa_donor_dashboard_page_id'))); ?>'; }
                    else { msgEl.textContent = j.message || 'Error'; }
                }catch(err){ msgEl.textContent = 'Error'; }
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    public function dashboard($atts = [], $content = ''): string
    {
        if (!is_user_logged_in()) {
            return sprintf('<p>%s <a href="%s">%s</a></p>',
                esc_html__('Please log in to view your dashboard.','fa-fundraising'),
                esc_url(get_permalink((int) get_option('fa_donor_login_page_id'))),
                esc_html__('Login','fa-fundraising')
            );
        }
        $nonce = wp_create_nonce('wp_rest');

        ob_start(); ?>
        <div class="fa-card">
            <h3><?php esc_html_e('My Dashboard','fa-fundraising'); ?></h3>

            <div id="fa-kpis" style="display:grid;grid-template-columns:repeat(2,minmax(180px,1fr));gap:12px;">
                <div class="fa-kpi"><strong><?php esc_html_e('Lifetime Total','fa-fundraising'); ?>:</strong> <span id="k_lifetime">—</span></div>
                <div class="fa-kpi"><strong><?php esc_html_e('Month to Date','fa-fundraising'); ?>:</strong> <span id="k_mtd">—</span></div>
                <div class="fa-kpi"><strong><?php esc_html_e('Last Month','fa-fundraising'); ?>:</strong> <span id="k_lm">—</span></div>
                <div class="fa-kpi"><strong><?php esc_html_e('Active Sponsorships','fa-fundraising'); ?>:</strong> <span id="k_active">—</span></div>
            </div>

            <h4 style="margin-top:1rem;"><?php esc_html_e('Breakdown','fa-fundraising'); ?></h4>
            <ul id="fa-breakdown" style="margin:.2rem 0 1rem 1rem;">
                <li><?php esc_html_e('General','fa-fundraising'); ?>: <span id="b_gen">—</span></li>
                <li><?php esc_html_e('Cause','fa-fundraising'); ?>: <span id="b_cause">—</span></li>
                <li><?php esc_html_e('Sponsorship','fa-fundraising'); ?>: <span id="b_spon">—</span></li>
            </ul>

            <div id="fa-series" style="margin-top:1rem;">
                <h4><?php esc_html_e('Donations (last 12 months)','fa-fundraising'); ?></h4>
                <div id="fa-series-labels" style="font-size:.9rem;opacity:.8;"></div>
                <div id="fa-series-data" style="font-family:monospace;"></div>
            </div>

            <div id="fa-subs" style="margin-top:1rem;">
                <h4><?php esc_html_e('My Subscriptions','fa-fundraising'); ?></h4>
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th style="border-bottom:1px solid #ddd;padding:8px;"><?php esc_html_e('Subscription ID','fa-fundraising'); ?></th>
                            <th style="border-bottom:1px solid #ddd;padding:8px;"><?php esc_html_e('Status','fa-fundraising'); ?></th>
                            <th style="border-bottom:1px solid #ddd;padding:8px;"><?php esc_html_e('Current Period','fa-fundraising'); ?></th>
                            <th style="border-bottom:1px solid #ddd;padding:8px;"><?php esc_html_e('Manage','fa-fundraising'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="fa-subs-body"></tbody>
                </table>
            </div>
        </div>

        <script>
        (function(){
            const api = (p)=> (window.wpApiSettings?.root || '/wp-json/') + 'faf/v1' + p;
            const money = (v)=> new Intl.NumberFormat(undefined,{style:'currency',currency:'INR',maximumFractionDigits:0}).format(v||0);

            async function loadSummary(){
                const r = await fetch(api('/stats/summary'), {headers:{'X-WP-Nonce':'<?php echo esc_js($nonce); ?>'}});
                const j = await r.json();
                if (!j.ok) return;
                document.getElementById('k_lifetime').textContent = money(j.lifetime_total);
                document.getElementById('k_mtd').textContent = money(j.month_to_date);
                document.getElementById('k_lm').textContent = money(j.last_month);
                document.getElementById('k_active').textContent = j.active_sponsorships;

                const bd = j.breakdown || {};
                const fmt = (o)=> (o && typeof o.amount !== 'undefined') ? money(o.amount) + ' ('+(o.count||0)+')' : '—';
                document.getElementById('b_gen').textContent = fmt(bd.general);
                document.getElementById('b_cause').textContent = fmt(bd.cause);
                document.getElementById('b_spon').textContent = fmt(bd.sponsorship);
            }

            async function loadSeries(){
                const r = await fetch(api('/stats/series'), {headers:{'X-WP-Nonce':'<?php echo esc_js($nonce); ?>'}});
                const j = await r.json();
                if (!j.ok) return;
                document.getElementById('fa-series-labels').textContent = j.labels.join('  |  ');
                document.getElementById('fa-series-data').textContent = j.data.map(v=>money(v)).join('  |  ');
            }

            async function loadSubs(){
                const r = await fetch(api('/subscriptions'), {headers:{'X-WP-Nonce':'<?php echo esc_js($nonce); ?>'}});
                const j = await r.json();
                const body = document.getElementById('fa-subs-body');
                body.innerHTML = '';
                if (!j.ok || !j.items?.length){
                    body.innerHTML = '<tr><td colspan="4" style="padding:10px;"><?php echo esc_js(__('No subscriptions found.','fa-fundraising')); ?></td></tr>';
                    return;
                }
                j.items.forEach(s=>{
                    const period = (s.current_start? new Date(s.current_start+'Z').toLocaleDateString():'—') + ' → ' + (s.current_end? new Date(s.current_end+'Z').toLocaleDateString():'—');
                    const manage = s.manage_url ? `<a href="${s.manage_url}" target="_blank"><?php echo esc_js(__('Manage','fa-fundraising')); ?></a>` : '—';
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                      <td style="padding:8px;border-bottom:1px solid #eee;">${s.razorpay_subscription_id}</td>
                      <td style="padding:8px;border-bottom:1px solid #eee;">${s.status}</td>
                      <td style="padding:8px;border-bottom:1px solid #eee;">${period}</td>
                      <td style="padding:8px;border-bottom:1px solid #eee;">${manage}</td>
                    `;
                    body.appendChild(tr);
                });
            }

            loadSummary();
            loadSeries();
            loadSubs();
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    public function receipts($atts = [], $content = ''): string
    {
        if (!is_user_logged_in()) {
            return sprintf('<p>%s <a href="%s">%s</a></p>',
                esc_html__('Please log in to view your receipts.','fa-fundraising'),
                esc_url(get_permalink((int) get_option('fa_donor_login_page_id'))),
                esc_html__('Login','fa-fundraising')
            );
        }
        $nonce = wp_create_nonce('wp_rest');

        ob_start(); ?>
        <div class="fa-card">
            <h3><?php esc_html_e('My Donations & Receipts','fa-fundraising'); ?></h3>

            <div style="display:flex;gap:.5rem;align-items:end;flex-wrap:wrap;margin:.5rem 0 1rem;">
                <div>
                    <label><?php esc_html_e('Type','fa-fundraising'); ?></label><br>
                    <select id="fa-don-type"><option value=""><?php esc_html_e('All','fa-fundraising'); ?></option><option value="general">General</option><option value="cause">Cause</option><option value="sponsorship">Sponsorship</option></select>
                </div>
                <div>
                    <label><?php esc_html_e('Status','fa-fundraising'); ?></label><br>
                    <select id="fa-don-status"><option value=""><?php esc_html_e('All','fa-fundraising'); ?></option><option value="captured">Captured</option><option value="pending">Pending</option><option value="failed">Failed</option></select>
                </div>
                <div>
                    <label><?php esc_html_e('From','fa-fundraising'); ?></label><br>
                    <input type="date" id="fa-don-start">
                </div>
                <div>
                    <label><?php esc_html_e('To','fa-fundraising'); ?></label><br>
                    <input type="date" id="fa-don-end">
                </div>
                <button id="fa-don-apply" style="padding:.5rem 1rem;"><?php esc_html_e('Apply','fa-fundraising'); ?></button>
            </div>

            <div style="overflow:auto;">
                <table class="fa-table" style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th style="border-bottom:1px solid #ddd;padding:8px;"><?php esc_html_e('Date','fa-fundraising'); ?></th>
                            <th style="border-bottom:1px solid #ddd;padding:8px;"><?php esc_html_e('Type','fa-fundraising'); ?></th>
                            <th style="border-bottom:1px solid #ddd;padding:8px;"><?php esc_html_e('Amount','fa-fundraising'); ?></th>
                            <th style="border-bottom:1px solid #ddd;padding:8px;"><?php esc_html_e('Status','fa-fundraising'); ?></th>
                            <th style="border-bottom:1px solid #ddd;padding:8px;"><?php esc_html_e('Receipt','fa-fundraising'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="fa-don-body"></tbody>
                </table>
            </div>

            <div id="fa-don-page" style="margin-top:.6rem;"></div>
            <p id="fa-don-msg" class="muted"></p>
        </div>

        <script>
        (function(){
            const api = (p)=> (window.wpApiSettings?.root || '/wp-json/') + 'faf/v1' + p;
            const money = (v, cur='INR')=> new Intl.NumberFormat(undefined,{style:'currency',currency:cur,maximumFractionDigits:0}).format(v||0);

            let page = 1, per_page = 10;

            async function load(){
                const type = document.getElementById('fa-don-type').value;
                const status = document.getElementById('fa-don-status').value;
                const start = document.getElementById('fa-don-start').value;
                const end = document.getElementById('fa-don-end').value;
                const q = new URLSearchParams({page, per_page});
                if (type) q.append('type', type);
                if (status) q.append('status', status);
                if (start) q.append('start', start);
                if (end) q.append('end', end);

                const r = await fetch(api('/donations?'+q.toString()), {headers:{'X-WP-Nonce':'<?php echo esc_js($nonce); ?>'}});
                const j = await r.json();
                const body = document.getElementById('fa-don-body');
                const pag  = document.getElementById('fa-don-page');
                body.innerHTML='';

                if (!j.ok || !j.items?.length){
                    body.innerHTML = '<tr><td colspan="5" style="padding:10px;"><?php echo esc_js(__('No donations found.','fa-fundraising')); ?></td></tr>';
                    pag.textContent='';
                    return;
                }

                j.items.forEach(it=>{
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td style="padding:8px;border-bottom:1px solid #eee;">${new Date(it.created_at+'Z').toLocaleDateString()}</td>
                        <td style="padding:8px;border-bottom:1px solid #eee;">${it.type}</td>
                        <td style="padding:8px;border-bottom:1px solid #eee;">${money(it.amount, it.currency)}</td>
                        <td style="padding:8px;border-bottom:1px solid #eee;">${it.status}</td>
                        <td style="padding:8px;border-bottom:1px solid #eee;">
                            ${it.status==='captured' ? `
                              <a href="${api('/receipt/'+it.id+'?type=basic')}" target="_blank"><?php echo esc_js(__('Download Basic','fa-fundraising')); ?></a>
                              &nbsp;|&nbsp;
                              <a href="${api('/receipt/'+it.id+'?type=80g')}" target="_blank"><?php echo esc_js(__('Download 80G','fa-fundraising')); ?></a>
                            ` : '<?php echo esc_js(__('—','fa-fundraising')); ?>'}
                        </td>
                    `;
                    body.appendChild(tr);
                });

                // pagination
                const totalPages = Math.ceil(j.total / j.per_page);
                pag.innerHTML = '';
                if (totalPages > 1){
                    for (let i=1;i<=totalPages;i++){
                        const btn = document.createElement('button');
                        btn.textContent = i;
                        btn.style = 'margin-right:6px;padding:.3rem .6rem;'+(i===page?'font-weight:bold;':'');
                        btn.onclick = ()=>{ page=i; load(); };
                        pag.appendChild(btn);
                    }
                }
            }

            document.getElementById('fa-don-apply').addEventListener('click', ()=>{ page=1; load(); });
            load();
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    public function settings($atts = [], $content = ''): string
    {
        if (!is_user_logged_in()) {
            return sprintf('<p>%s <a href="%s">%s</a></p>',
                esc_html__('Please log in to edit your settings.','fa-fundraising'),
                esc_url(get_permalink((int) get_option('fa_donor_login_page_id'))),
                esc_html__('Login','fa-fundraising')
            );
        }
        $u = wp_get_current_user();
        $nonce = wp_create_nonce('wp_rest');

        $meta = [
            'phone' => get_user_meta($u->ID,'fa_phone',true),
            'pan'   => get_user_meta($u->ID,'fa_pan',true),
            'address_line1'=> get_user_meta($u->ID,'fa_address_line1',true),
            'address_line2'=> get_user_meta($u->ID,'fa_address_line2',true),
            'city'=> get_user_meta($u->ID,'fa_city',true),
            'state'=> get_user_meta($u->ID,'fa_state',true),
            'pin'=> get_user_meta($u->ID,'fa_pin',true),
            'country'=> get_user_meta($u->ID,'fa_country',true),
        ];

        ob_start(); ?>
        <div class="fa-card" style="max-width:640px;">
            <h3><?php esc_html_e('My Settings','fa-fundraising'); ?></h3>
            <form id="fa-settings-form" style="display:grid;gap:.6rem;">
                <label><?php esc_html_e('Name','fa-fundraising'); ?></label>
                <input type="text" name="name" value="<?php echo esc_attr($u->display_name); ?>" style="padding:.6rem;">
                <label><?php esc_html_e('Phone','fa-fundraising'); ?></label>
                <input type="text" name="phone" value="<?php echo esc_attr($meta['phone']); ?>" style="padding:.6rem;">
                <label><?php esc_html_e('PAN (for 80G)','fa-fundraising'); ?></label>
                <input type="text" name="pan" value="<?php echo esc_attr($meta['pan']); ?>" style="padding:.6rem;">
                <label><?php esc_html_e('Address Line 1','fa-fundraising'); ?></label>
                <input type="text" name="address_line1" value="<?php echo esc_attr($meta['address_line1']); ?>" style="padding:.6rem;">
                <label><?php esc_html_e('Address Line 2','fa-fundraising'); ?></label>
                <input type="text" name="address_line2" value="<?php echo esc_attr($meta['address_line2']); ?>" style="padding:.6rem;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;">
                    <div>
                        <label><?php esc_html_e('City','fa-fundraising'); ?></label>
                        <input type="text" name="city" value="<?php echo esc_attr($meta['city']); ?>" style="padding:.6rem;">
                    </div>
                    <div>
                        <label><?php esc_html_e('State','fa-fundraising'); ?></label>
                        <input type="text" name="state" value="<?php echo esc_attr($meta['state']); ?>" style="padding:.6rem;">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;">
                    <div>
                        <label><?php esc_html_e('PIN Code','fa-fundraising'); ?></label>
                        <input type="text" name="pin" value="<?php echo esc_attr($meta['pin']); ?>" style="padding:.6rem;">
                    </div>
                    <div>
                        <label><?php esc_html_e('Country','fa-fundraising'); ?></label>
                        <input type="text" name="country" value="<?php echo esc_attr($meta['country']); ?>" style="padding:.6rem;">
                    </div>
                </div>
                <button type="submit" style="padding:.7rem 1.2rem;"><?php esc_html_e('Save Settings','fa-fundraising'); ?></button>
                <p id="fa-settings-msg" style="margin:.5rem 0 0;"></p>
            </form>
        </div>
        <script>
        (function(){
            const form = document.getElementById('fa-settings-form');
            const msg  = document.getElementById('fa-settings-msg');
            const api  = (p)=> (window.wpApiSettings?.root || '/wp-json/') + 'faf/v1' + p;
            form?.addEventListener('submit', async (e)=>{
                e.preventDefault();
                msg.textContent = '<?php echo esc_js(__('Saving...','fa-fundraising')); ?>';
                const data = Object.fromEntries(new FormData(form).entries());
                try{
                    const r = await fetch(api('/me'), {
                        method:'POST',
                        headers:{
                            'Content-Type':'application/json',
                            'X-WP-Nonce':'<?php echo esc_js($nonce); ?>'
                        },
                        body: JSON.stringify(data)
                    });
                    const j = await r.json();
                    msg.textContent = j.ok ? '<?php echo esc_js(__('Saved.','fa-fundraising')); ?>' : (j.message || 'Error');
                }catch(err){
                    msg.textContent = 'Error';
                }
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
