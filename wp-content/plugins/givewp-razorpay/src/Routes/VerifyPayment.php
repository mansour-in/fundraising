<?php
namespace FA\GiveRazorpay\Routes;

use Razorpay\Api\Api;
use WP_REST_Request;
use Give\Donations\Models\Donation;

class VerifyPayment {
    public function register(){
        register_rest_route('givewp-razorpay/v1','/verify',[
            'methods'=>'POST','callback'=>[$this,'handle'],'permission_callback'=>'__return_true'
        ]);
    }
    public function handle(WP_REST_Request $r){
        $donationId = (int)$r['donationId'];
        $orderId    = sanitize_text_field($r['orderId']);
        $paymentId  = sanitize_text_field($r['paymentId']);
        $signature  = sanitize_text_field($r['signature']);

        $donation = Donation::find($donationId);
        if (!$donation) return new \WP_Error('notfound','Donation not found',['status'=>404]);

        $mode = get_post_meta($donationId,'_give_razorpay_mode',true) ?: 'test';
        $pub  = $mode==='live' ? give_get_option('givewp_gateway_razorpay_live_key') : give_get_option('givewp_gateway_razorpay_test_key');
        $sec  = $mode==='live' ? give_get_option('givewp_gateway_razorpay_live_secret') : give_get_option('givewp_gateway_razorpay_test_secret');

        $api = new Api($pub,$sec);
        try {
            $api->utility->verifyPaymentSignature([
                'razorpay_order_id'   => $orderId,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature'  => $signature
            ]);
        } catch (\Throwable $e) {
            return new \WP_Error('sig','Invalid signature',['status'=>401]);
        }

        $status = 'captured';
        try { $status = $api->payment->fetch($paymentId)['status']; } catch (\Throwable $e) {}

        if (!in_array($status,['captured','authorized'], true)) {
            return ['ok'=>false,'status'=>$status];
        }

        if ($donation->status->isPending()) {
            $donation->status = 'publish';
            $donation->gatewayTransactionId = $paymentId;
            $donation->save();
        }

        update_post_meta($donationId,'_give_razorpay_payment_id',$paymentId);
        update_post_meta($donationId,'_give_razorpay_order_id',$orderId);

        return ['ok'=>true];
    }
}
