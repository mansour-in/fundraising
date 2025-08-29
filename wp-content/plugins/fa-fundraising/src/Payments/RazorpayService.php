<?php
namespace FA\Fundraising\Payments;

use Razorpay\Api\Api;

if (!defined('ABSPATH')) exit;

class RazorpayService {
    private string $key_id;
    private string $key_secret;
    private string $webhook_secret;

    public function __construct() {
        $this->key_id        = (string) get_option('fa_rzp_key_id', '');
        $this->key_secret    = (string) get_option('fa_rzp_key_secret', '');
        $this->webhook_secret= (string) get_option('fa_rzp_webhook_secret', '');
    }

    public function keyId(): string { return $this->key_id; }

    private function api(): Api {
        return new Api($this->key_id, $this->key_secret);
    }

    /** $amount_rupees decimal(2), returns Razorpay order array */
    public function create_order(float $amount_rupees, string $currency='INR', array $notes=[]): array {
        $a_paise = (int) round($amount_rupees * 100);
        $receipt = 'FA-'.time().'-'.wp_generate_password(6,false,false);
        $order = $this->api()->order->create([
            'amount' => $a_paise,
            'currency' => $currency,
            'receipt' => $receipt,
            'payment_capture' => 1,
            'notes' => $notes,
        ]);
        return $order->toArray();
    }

    /** Verify webhook signature, throws on mismatch */
    public function verify_webhook(string $payload, string $sig): void {
        $this->api()->utility->verifyWebhookSignature($payload, $sig, $this->webhook_secret);
    }

    public static function fy_from_date(string $date): string {
        $t = strtotime($date);
        $y = (int)gmdate('Y',$t); $m = (int)gmdate('n',$t);
        if ($m < 4) return sprintf('%d-%02d', $y-1, ($y%100));
        return sprintf('%d-%02d', $y, (($y+1)%100));
    }
}
