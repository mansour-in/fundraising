<?php
namespace FA\GiveRazorpay\Routes;

use Razorpay\Api\Utility;
use WP_REST_Request;
use Give\Donations\Models\Donation;

class Webhook {
    public function register(){
        register_rest_route('givewp-razorpay/v1','/webhook',[
            'methods'=>'POST','callback'=>[$this,'handle'],'permission_callback'=>'__return_true'
        ]);
    }
    public function handle(WP_REST_Request $r){
        $body = $r->get_body();
        $sig = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
        $secret = give_get_option('givewp_gateway_razorpay_webhook_secret');
        if (!$secret) return new \WP_Error('cfg','Webhook secret not set',['status'=>500]);

        try { Utility::verifyWebhookSignature($body, $sig, $secret); }
        catch (\Throwable $e) { return new \WP_Error('sig','Bad signature',['status'=>401]); }

        $data = json_decode($body, true);
        $event = $data['event'] ?? '';
        if ($event === 'payment.captured' || $event === 'payment.authorized') {
            $entity = $data['payload']['payment']['entity'] ?? [];
            $orderId = $entity['order_id'] ?? '';
            $paymentId = $entity['id'] ?? '';

            if ($orderId) {
                $donations = get_posts([
                    'post_type'=>'give_payment','numberposts'=>1,'fields'=>'ids',
                    'meta_query'=>[['key'=>'_give_razorpay_order_id','value'=>$orderId]]
                ]);
                if ($donations) {
                    $id = (int)$donations[0];
                    $donation = Donation::find($id);
                    if ($donation && $donation->status->isPending()) {
                        $donation->status = 'publish';
                        $donation->gatewayTransactionId = $paymentId;
                        $donation->save();
                    }
                    update_post_meta($id,'_give_razorpay_payment_id',$paymentId);
                }
            }
        }
        return ['ok'=>true];
    }
}
