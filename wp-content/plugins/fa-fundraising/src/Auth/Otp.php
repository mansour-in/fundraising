<?php
namespace FA\Fundraising\Auth;

use WP_Error;
use WP_REST_Request;

if (!defined('ABSPATH')) exit;

class Otp {

    public static function request_otp(WP_REST_Request $req) {
        $email = sanitize_email($req->get_param('email'));
        if (!$email || !is_email($email)) return new WP_Error('bad_email','Invalid email', ['status'=>400]);

        if (self::rate_limited('otp', $email)) return new WP_Error('rate','Too many attempts. Try later.', ['status'=>429]);

        $user_id = MagicLink::try_consume_from_query(); // no-op here, just keeping file inclusion warm
        $user_id = self::get_or_create_donor_user($email);

        $otp_plain = (string) random_int(100000, 999999);
        $hash = wp_hash_password($otp_plain);
        $exp  = gmdate('Y-m-d H:i:s', time() + 10*60);

        global $wpdb;
        $table = $wpdb->prefix.'fa_auth_tokens';
        $wpdb->insert($table, [
            'email'      => $email,
            'user_id'    => $user_id,
            'type'       => 'otp',
            'secret'     => $hash,
            'expires_at' => $exp,
            'used'       => 0,
            'meta'       => maybe_serialize(['ip'=>self::ip()]),
        ]);

        $subj = __('Your OTP for Future Achievers','fa-fundraising');
        $msg  = sprintf(__("Your OTP is: %s\n\nIt expires in 10 minutes.",'fa-fundraising'), $otp_plain);
        wp_mail($email, $subj, $msg);

        return ['ok'=>true, 'sent'=>true];
    }

    public static function verify_otp(WP_REST_Request $req) {
        $email = sanitize_email($req->get_param('email'));
        $otp   = sanitize_text_field($req->get_param('otp'));
        if (!$email || !is_email($email) || !$otp) return new WP_Error('bad','Invalid request',['status'=>400]);

        global $wpdb;
        $table = $wpdb->prefix.'fa_auth_tokens';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE type='otp' AND email=%s AND used=0 AND expires_at >= UTC_TIMESTAMP() ORDER BY id DESC LIMIT 10",
            $email
        ));

        foreach ($rows as $r) {
            if (wp_check_password($otp, $r->secret)) {
                $wpdb->update($table, ['used'=>1], ['id'=>$r->id]);
                $user = get_user_by('email', $email);
                if ($user) {
                    wp_set_current_user($user->ID);
                    wp_set_auth_cookie($user->ID, true);
                    return ['ok'=>true];
                }
            }
        }
        return new WP_Error('bad_otp','Invalid or expired OTP',['status'=>400]);
    }

    private static function get_or_create_donor_user(string $email): int {
        $u = get_user_by('email', $email);
        if ($u) return (int)$u->ID;
        $username = sanitize_user(current(explode('@',$email)).'_'.wp_generate_password(6,false,false), true);
        $pass = wp_generate_password(20, true, true);
        $uid  = wp_create_user($username, $pass, $email);
        if (is_wp_error($uid)) return 0;
        $wu = new \WP_User($uid);
        $wu->set_role('fa_donor');
        wp_update_user(['ID'=>$uid, 'display_name'=>current(explode('@',$email))]);
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
