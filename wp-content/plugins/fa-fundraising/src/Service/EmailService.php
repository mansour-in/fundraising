<?php
namespace FA\Fundraising\Service;

use FA\Fundraising\Pdf\Renderer;

if (!defined('ABSPATH')) exit;

class EmailService {

    public static function send_thankyou_with_receipt(array $donation): void {
        $to   = $donation['donor_email'] ?: '';
        if (!$to) return;

        $org  = get_bloginfo('name');
        $subj = get_option('fa_email_thankyou_subject',
            sprintf(__('Thank you for donating to %s','fa-fundraising'), $org)
        );
        $bodyTpl = get_option('fa_email_thankyou_body',
            "Dear {{name}},\n\nThank you for your contribution of {{amount}} on {{date}}.\n\nAttached is your {{receipt_type}} receipt.\n\nWarm regards,\n{{org}}"
        );

        $amount = number_format((float)$donation['amount'], 2).' '.$donation['currency'];
        $date   = gmdate('d M Y', strtotime($donation['created_at'] ?? 'now'));
        $name   = $donation['donor_name'] ?: $to;
        $receiptType = 'donation';
        $orgName = $org;

        // Choose PDF type: 80G if donor PAN exists
        $pan = $donation['user_id'] ? get_user_meta((int)$donation['user_id'],'fa_pan',true) : '';
        $pdf_type = $pan ? '80g' : 'basic';
        $path = Renderer::generate($donation, $pdf_type);

        $repl = [
            '{{name}}' => $name,
            '{{amount}}' => $amount,
            '{{date}}' => $date,
            '{{receipt_type}}' => strtoupper($pdf_type),
            '{{org}}' => $orgName,
        ];
        $body = strtr($bodyTpl, $repl);

        $headers = [];
        $from = get_option('fa_email_from', '');
        if ($from) $headers[] = 'From: '.$from;
        $bcc = get_option('fa_email_bcc', '');
        if ($bcc) $headers[] = 'Bcc: '.$bcc;

        // Attach
        add_filter('wp_mail_content_type', fn()=> 'text/plain');
        wp_mail($to, $subj, $body, $headers, [$path]);
        remove_filter('wp_mail_content_type', '__return_true');
    }
}
