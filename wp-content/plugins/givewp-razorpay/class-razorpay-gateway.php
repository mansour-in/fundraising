<?php

use Give\Donations\Models\Donation;
use Give\Donations\Models\DonationNote;
use Give\Donations\ValueObjects\DonationStatus;
use Give\Framework\Http\Response\Types\RedirectResponse;
use Give\Framework\PaymentGateways\Commands\PaymentRefunded;
use Give\Framework\PaymentGateways\PaymentGateway;

/**
 * Razorpay payment gateway implementation.
 */
class RazorpayGateway extends PaymentGateway {

    /**
     * {@inheritdoc}
     */
    public static function id(): string {
        return 'razorpay';
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): string {
        return self::id();
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string {
        return __( 'Razorpay', 'givewp-razorpay' );
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethodLabel(): string {
        return __( 'Razorpay', 'givewp-razorpay' );
    }

    /**
     * {@inheritdoc}
     */
    public function getLegacyFormFieldMarkup( int $formId, array $args ): string {
        return '<div class="givewp-razorpay-help-text"><p>' . esc_html__( 'Pay securely via Razorpay.', 'givewp-razorpay' ) . '</p></div>';
    }

    /**
     * Process the donation with Razorpay.
     *
     * This is a stub that immediately marks the donation as complete. Replace
     * with real Razorpay API integration as needed.
     */
    public function createPayment( Donation $donation, $gatewayData ) {
        $donation->status = DonationStatus::COMPLETE();
        $donation->gatewayTransactionId = 'razorpay-demo';
        $donation->save();

        DonationNote::create([
            'donationId' => $donation->id,
            'content'    => __( 'Donation processed via Razorpay test gateway.', 'givewp-razorpay' ),
        ]);

        return new RedirectResponse( give_get_success_page_uri() );
    }

    /**
     * {@inheritdoc}
     */
    public function refundDonation( Donation $donation ): PaymentRefunded {
        return new PaymentRefunded();
    }
}

