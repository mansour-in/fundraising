<?php
namespace FA\Fundraising\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;

if (!defined('ABSPATH')) exit;

class Renderer {
    public static function generate(array $donation, string $type = 'basic'): string
    {
        $upload_dir = wp_upload_dir();
        $base = trailingslashit($upload_dir['basedir']).'fa-receipts';
        wp_mkdir_p($base);

        $fy = self::fy_from_date($donation['created_at'] ?? gmdate('Y-m-d'));
        $dir = $base . '/' . $fy;
        wp_mkdir_p($dir);

        $filename = sprintf('receipt-%s-%s.pdf', $donation['id'], $type === '80g' ? '80g' : 'basic');
        $path = $dir . '/' . $filename;

        $org  = get_bloginfo('name');
        $addr = get_option('fa_org_address', '');
        $logo = get_option('fa_org_logo_url', '');
        $pan_org = get_option('fa_org_pan', '');
        $reg_80g = get_option('fa_org_80g_reg', '');

        $amount = number_format((float)$donation['amount'], 2);
        $date   = gmdate('d M Y', strtotime($donation['created_at'] ?? 'now'));
        $donor  = esc_html($donation['donor_name'] ?: $donation['donor_email']);
        $receipt_no = esc_html($donation['receipt_no'] ?: ('FA-' . $donation['id']));
        $payment_id = esc_html($donation['razorpay_payment_id'] ?: '');

        $html = '<html><head><meta charset="utf-8"><style>
            body{font-family:DejaVu Sans, Arial, sans-serif;font-size:12px;color:#222;margin:24px;}
            h1{font-size:20px;margin:0 0 8px;}
            .muted{color:#666}
            .row{display:flex;justify-content:space-between;gap:12px}
            .box{border:1px solid #ccc;padding:12px;border-radius:6px;margin-top:12px}
            table{width:100%;border-collapse:collapse;margin-top:12px}
            th,td{border:1px solid #ddd;padding:8px;text-align:left}
            .right{text-align:right}
        </style></head><body>';

        $html .= '<div class="row"><div>';
        $html .= '<h1>'.($type==='80g' ? '80G Donation Certificate' : 'Donation Receipt').'</h1>';
        $html .= '<div class="muted">Receipt No: '.$receipt_no.' &nbsp; | &nbsp; Date: '.$date.'</div>';
        $html .= '</div><div>';
        if ($logo) $html .= '<img src="'.esc_url($logo).'" style="max-height:60px;">';
        $html .= '</div></div>';

        $html .= '<div class="box"><strong>'.$org.'</strong><br>'.nl2br(esc_html($addr)).'</div>';

        $html .= '<div class="box"><strong>Donor:</strong> '.$donor.'<br><span class="muted">'.$donation['donor_email'].'</span></div>';

        $html .= '<table><thead><tr><th>Description</th><th class="right">Amount ('.$donation['currency'].')</th></tr></thead><tbody>';
        $desc = ucfirst($donation['type']);
        if (!empty($donation['orphan_id'])) $desc .= ' (Orphan #'.$donation['orphan_id'].')';
        if (!empty($donation['cause_id']))  $desc .= ' (Cause #'.$donation['cause_id'].')';
        $html .= '<tr><td>'.$desc.'</td><td class="right">'.$amount.'</td></tr>';
        $html .= '</tbody></table>';

        if ($type === '80g') {
            $donor_pan = get_user_meta((int)$donation['user_id'], 'fa_pan', true);
            $html .= '<div class="box"><strong>80G Declaration</strong><br>
                This is to certify that the above donation is received towards charitable purposes under Section 80G of the Income Tax Act.
                <br>Org PAN: '.esc_html($pan_org).' | 80G Reg.: '.esc_html($reg_80g).'
                <br>Donor PAN: '.esc_html($donor_pan ?: '—').'
            </div>';
        } else {
            $html .= '<div class="box muted">This receipt acknowledges the contribution above. Thank you for your support.</div>';
        }

        if ($payment_id) {
            $verify_url = site_url('/wp-json/faf/v1/verify/receipt?payment_id='.rawurlencode($payment_id));
            $html .= '<div class="muted">Payment Ref: '.$payment_id.' | Verify: '.$verify_url.'</div>';
        }

        $html .= '</body></html>';

        $opt = new Options();
        $opt->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($opt);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        file_put_contents($path, $dompdf->output());
        return $path;
    }

    private static function fy_from_date(string $date): string {
        $t = strtotime($date);
        $y = (int)gmdate('Y',$t); $m = (int)gmdate('n',$t);
        // India FY: Apr(4)–Mar(3)
        if ($m < 4) return sprintf('%d-%02d', $y-1, ($y%100));
        return sprintf('%d-%02d', $y, (($y+1)%100));
    }
}
