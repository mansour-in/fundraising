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
        ob_start(); ?>
        <div class="fa-card">
            <h3><?php esc_html_e('My Dashboard','fa-fundraising'); ?></h3>
            <p><?php esc_html_e('KPI cards, charts, and tables will appear here in later steps.','fa-fundraising'); ?></p>
        </div>
        <?php return ob_get_clean();
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
        ob_start(); ?>
        <div class="fa-card">
            <h3><?php esc_html_e('My Receipts','fa-fundraising'); ?></h3>
            <p><?php esc_html_e('Receipt list and downloads will appear here.','fa-fundraising'); ?></p>
        </div>
        <?php return ob_get_clean();
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
        ob_start(); ?>
        <div class="fa-card">
            <h3><?php esc_html_e('My Settings','fa-fundraising'); ?></h3>
            <p><?php esc_html_e('Profile, PAN, address, privacy tools coming soon.','fa-fundraising'); ?></p>
        </div>
        <?php return ob_get_clean();
    }
}
