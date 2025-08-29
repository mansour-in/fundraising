<?php
namespace FA\Fundraising\Auth;

use WP_Error;
use WP_REST_Request;

if (!defined('ABSPATH')) exit;

class MagicLink {

    public static function request_link(WP_REST_Request $req) {
        $email = sanitize_email($req->get_param('email'));
        if (!$email || !is_email($email)) return new WP_Error('bad_email','Invalid email', ['status'=>400]);

        if (self::rate_limited('ml', $email)) return new WP_Error('rate','Too many attempts. Try later.', ['status'=>429]);

        $user_id = self::get_or_create_donor_user($email);

        $token = wp_generate_password(43, false, false);
        $hash  = wp_hash_password($token);
        $exp   = gmdate('Y-m-d H:i:s', time() + 10*60);

        global $wpdb;
        $table = $wpdb->prefix.'fa_auth_tokens';
        $wpdb->insert($table, [
            'email'      => $email,
            'user_id'    => $user_id,
            'type'       => 'magic',
            'secret'     => $hash,
            'expires_at' => $exp,
            'used'       => 0,
            'meta'       => maybe_serialize(['ip'=>self::ip()]),
        ]);

        $login_url = add_query_arg('token', rawurlencode($token), get_permalink((int) get_option('fa_donor_login_page_id')));

        $subj = __('Your login link for Future Achievers','fa-fundraising');
        $msg  = sprintf(
            __("Click to sign in:\n\n%s\n\nThis link expires in 10 minutes.",'fa-fundraising'),
            esc_url($login_url)
        );
        wp_mail($email, $subj, $msg);

        return ['ok'=>true, 'sent'=>true];
    }

    /** Validate token from the login page and sign the user in */
    public static function try_consume_from_query(): ?string {
        if (empty($_GET['token'])) return null;
        $token = sanitize_text_field(wp_unslash($_GET['token']));
        if (!$token) return __('Invalid token.','fa-fundraising');

        global $wpdb;
        $table = $wpdb->prefix.'fa_auth_tokens';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE type='magic' AND used=0 AND expires_at >= UTC_TIMESTAMP() ORDER BY id DESC LIMIT 25"
        ));

        // We need to scan recent valid rows for a matching hash (wp_check_password)
        if ($row) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE type='magic' AND used=0 AND expires_at >= UTC_TIMESTAMP() ORDER BY id DESC LIMIT 50"
            ));
            foreach ($rows as $r) {
                if (wp_check_password($token, $r->secret)) {
                    // consume
                    $wpdb->update($table, ['used'=>1], ['id'=>$r->id]);
                    $user = get_user_by('email', $r->email);
                    if ($user) {
                        wp_set_current_user($user->ID);
                        wp_set_auth_cookie($user->ID, true);
                        return 'OK';
                    }
                }
            }
        }
        return __('Link is invalid or expired.','fa-fundraising');
    }

    private static function get_or_create_donor_user(string $email): int {
        $user = get_user_by('email', $email);
        if ($user) return (int)$user->ID;

        $username = sanitize_user(current(explode('@',$email)).'_'.wp_generate_password(6,false,false), true);
        $pass = wp_generate_password(20, true, true);
        $uid  = wp_create_user($username, $pass, $email);
        if (is_wp_error($uid)) return 0;

        $u = new \WP_User($uid);
        $u->set_role('fa_donor');
        wp_update_user([
            'ID' => $uid,
            'display_name' => current(explode('@',$email))
        ]);
        return (int)$uid;
    }

    private static function rate_limited(string $k, string $email): bool {
        $key = 'faf_rl_'.$k.'_'.md5(self::ip().'|'.$email);
        $n = (int) get_transient($key);
        if ($n >= 5) return true;
        set_transient($key, $n+1, 15 * MINUTE_IN_SECONDS);
        return false;
    }

    private static function ip(): string {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
