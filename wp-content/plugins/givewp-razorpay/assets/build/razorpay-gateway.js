/* global givewp */
const { addAction } = window?.givewp?.hooks || { addAction: () => {} };

addAction('givewp-register-gateway', 'razorpay', ({ gateway }) => {
  return gateway({
    id: 'razorpay',
    initialize({ formSettings }) {
      this.pubKey = formSettings.key;
      this.createUrl = formSettings.createOrderUrl;
      this.verifyUrl = formSettings.verifyUrl;
    },
    async beforeCreatePayment({ donation, setGatewayData }) {
      const res = await fetch(this.createUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ donationId: donation.id })
      });
      const j = await res.json();
      if (!j.id) throw new Error('Order create failed');
      setGatewayData({ orderId: j.id });
      this.orderId = j.id;
    },
    async afterCreatePayment({ donation }) {
      const opts = {
        key: this.pubKey,
        order_id: this.orderId,
        name: document.title || 'Donation',
        handler: async (resp) => {
          const r = await fetch(this.verifyUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              donationId: donation.id,
              orderId: resp.razorpay_order_id,
              paymentId: resp.razorpay_payment_id,
              signature: resp.razorpay_signature
            })
          });
          const j = await r.json();
          if (!j.ok) throw new Error('Verification failed');
        }
      };
      const rz = new window.Razorpay(opts);
      rz.open();
    }
  });
});
