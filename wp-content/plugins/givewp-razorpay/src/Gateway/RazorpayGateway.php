<?php
namespace FA\GiveRazorpay\Gateway;

use Give\PaymentGateways\Gateway;
use Give\PaymentGateways\Commands\{PaymentComplete, PaymentFailed, RedirectOffsite, RespondToBrowser};
use Give\PaymentGateways\DataTransferObjects\GatewayPaymentData;
use Give\Donations\Models\Donation;
use FA\GiveRazorpay\Routes\CreateOrder;
use FA\GiveRazorpay\Routes\VerifyPayment;
use FA\GiveRazorpay\Routes\Webhook;

if (!defined('ABSPATH')) exit;

final class RazorpayGateway extends Gateway
{
    public static function id(): string { return 'razorpay'; }
    public static function name(): string { return __('Razorpay','givewp'); }
    public static function paymentMethodLabel(): string { return __('Razorpay','givewp'); }

    /** Admin settings screen (GiveWP → Settings → Payment Gateways → Razorpay) */
    public static function getAdminSettingsFormFields(): array {
        return [
            ['id'=>'mode','type'=>'radio','label'=>__('Mode','givewp'),'options'=>['test'=>'Test','live'=>'Live'],'default'=>'test'],
            ['id'=>'test_key','type'=>'text','label'=>__('Test Key ID','givewp')],
            ['id'=>'test_secret','type'=>'password','label'=>__('Test Key Secret','givewp')],
            ['id'=>'live_key','type'=>'text','label'=>__('Live Key ID','givewp')],
            ['id'=>'live_secret','type'=>'password','label'=>__('Live Key Secret','givewp')],
            ['id'=>'webhook_secret','type'=>'text','label'=>__('Webhook Secret','givewp')],
            ['id'=>'order_prefix','type'=>'text','label'=>__('Order Prefix','givewp'),'default'=>'GIVE']
        ];
    }

    /** Pass public data to the Visual Form Builder front-end */
    public function formSettings(int $formId): array {
        [$key] = self::keys()['pub'];
        return [
            'key' => $key,
            'createOrderUrl' => rest_url('givewp-razorpay/v1/create-order'),
            'verifyUrl'      => rest_url('givewp-razorpay/v1/verify'),
            'currency'       => give_get_currency(),
        ];
    }

    /** Load front-end script for Visual Donation Form Builder */
    public function enqueueScript(): void {
        wp_enqueue_script(
            'givewp-razorpay',
            FA_GIVERZP_URL.'assets/build/razorpay-gateway.js',
            ['wp-element','wp-i18n'],
            filemtime(FA_GIVERZP_DIR.'assets/build/razorpay-gateway.js'),
            true
        );
        wp_enqueue_script('rzp-checkout', 'https://checkout.razorpay.com/v1/checkout.js', [], null, true);
    }

    /** Legacy form field markup (option-based editor) */
    public function getLegacyFormFieldMarkup(int $formId, array $args): string {
        $s = $this->formSettings($formId);
        ob_start(); ?>
        <p><?php esc_html_e('You will complete your donation securely with Razorpay.','givewp'); ?></p>
        <script>window.__giveRzp=<?php echo wp_json_encode($s); ?>;</script>
        <?php return ob_get_clean();
    }

    /** Create payment (called by GiveWP on submit) */
    public function createPayment(Donation $donation, GatewayPaymentData $gatewayData) {
        // 1) Create Razorpay Order via REST route (server will persist order id against donation).
        //    For Visual Builder we already call /create-order before submit; for legacy forms, call now.
        if (empty($gatewayData->get('orderId'))) {
            $order = (new CreateOrder())->createForDonation($donation);
            if (is_wp_error($order)) {
                return new PaymentFailed($donation, new \Exception($order->get_error_message()));
            }
            $gatewayData->set('orderId', $order['id']);
        }

        // 2) Ask browser to open checkout (our JS listens and opens Razorpay with orderId).
        return new RespondToBrowser(
            $donation,
            ['action' => 'razorpay_open', 'orderId' => $gatewayData->get('orderId')]
        );
    }

    /** Secure route for success (VerifyPayment::handle) */
    protected array $secureRouteMethods = ['handlePaymentSuccess'];

    public function handlePaymentSuccess(Donation $donation, array $payload) {
        // server-side verification already performed in VerifyPayment route
        return new PaymentComplete($donation);
    }

    /** Public webhook URL for docs/UI */
    public static function webhookUrl(): string {
        $i = new static();
        return rest_url('givewp-razorpay/v1/webhook');
    }

    /** Helper: read settings */
    public static function keys(): array {
        $mode = give_get_option('razorpay_mode','test');
        if (function_exists('give_get_option')) {
            $mode = give_get_option('givewp_gateway_razorpay_mode','test') ?: 'test';
            $pub  = $mode==='live' ? give_get_option('givewp_gateway_razorpay_live_key') : give_get_option('givewp_gateway_razorpay_test_key');
            $sec  = $mode==='live' ? give_get_option('givewp_gateway_razorpay_live_secret') : give_get_option('givewp_gateway_razorpay_test_secret');
        } else { $pub = $sec = ''; }
        return ['pub'=>[$pub], 'sec'=>[$sec], 'mode'=>$mode];
    }

    /** Refund donation */
    public function refundDonation(Donation $donation, ?int $amountMinor = null){
        [$pub,$sec] = self::keys()['pub'];
        $api = new \Razorpay\Api\Api($pub,$sec);
        $txn = get_post_meta($donation->id(), '_give_razorpay_payment_id', true);
        if (!$txn) throw new \RuntimeException('Missing Razorpay payment id.');
        $amount = $amountMinor ?? (int)$donation->amount->formatToMinorAmount();
        $api->payment->fetch($txn)->refund(['amount'=>$amount]);
    }
}
