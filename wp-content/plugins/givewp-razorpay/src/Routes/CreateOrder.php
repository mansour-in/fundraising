<?php
namespace FA\GiveRazorpay\Routes;

use Razorpay\Api\Api;
use WP_REST_Request;
use Give\Donations\Models\Donation;

class CreateOrder {
    public function register(){
        register_rest_route('givewp-razorpay/v1','/create-order',[
            'methods'=>'POST','callback'=>[$this,'handle'],'permission_callback'=>'__return_true'
        ]);
    }
    public function handle(WP_REST_Request $r){
        $donationId = (int)($r['donationId'] ?? 0);
        $donation = Donation::find($donationId);
        if (!$donation) return new \WP_Error('notfound','Donation not found',['status'=>404]);

        [$pub,$sec,$mode] = $this->keys();
        if (!$pub || !$sec) return new \WP_Error('cfg','Missing Razorpay keys',['status'=>500]);

        $api = new Api($pub,$sec);
        $amountMinor = (int) $donation->amount->formatToMinorAmount(); // paise
        $order = $api->order->create([
            'amount' => $amountMinor,
            'currency' => $donation->amount->getCurrency()->getCode() ?: 'INR',
            'receipt' => 'GIVE-'.$donation->id,
            'payment_capture' => 1
        ]);

        update_post_meta($donationId, '_give_razorpay_order_id', $order['id']);
        update_post_meta($donationId, '_give_razorpay_mode', $mode);

        return ['id'=>$order['id'], 'key'=>$pub, 'amount'=>$amountMinor];
    }

    private function keys(): array {
        $mode = give_get_option('givewp_gateway_razorpay_mode','test') ?: 'test';
        $pub  = $mode==='live' ? give_get_option('givewp_gateway_razorpay_live_key') : give_get_option('givewp_gateway_razorpay_test_key');
        $sec  = $mode==='live' ? give_get_option('givewp_gateway_razorpay_live_secret') : give_get_option('givewp_gateway_razorpay_test_secret');
        return [$pub,$sec,$mode];
    }
}
