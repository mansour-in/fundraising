function(){
  const el = document.currentScript.previousElementSibling;
  const root = el.getAttribute('data-root');
  const district = el.getAttribute('data-district')||'';
  const status = el.getAttribute('data-status')||'';
  const per = el.getAttribute('data-per')||'12';
  const q = new URLSearchParams({per_page: per});
  if (district) q.append('district', district);
  if (status) q.append('status', status);

  fetch(root + '/orphans?' + q.toString())
    .then(r=>r.json()).then(j=>{
      el.innerHTML='';
      if (!j.ok || !j.items?.length) {
        el.innerHTML = '<div style="grid-column:1/-1;">No orphans found.</div>';
        return;
      }
      j.items.forEach(o=>{
        const card = document.createElement('div');
        card.className='fa-card';
        card.style='border:1px solid #e5e7eb;border-radius:12px;padding:12px;background:#fff;display:flex;flex-direction:column;gap:8px;';
        card.innerHTML = `
          <img src="${o.image||''}" alt="" style="width:100%;height:140px;object-fit:cover;border-radius:8px;">
          <div><strong>${o.name}</strong><br><span style="opacity:.7;">Age: ${o.age||'—'} | ${o.districts?.[0]||''}</span></div>
          <div style="font-size:.95rem;">Monthly Cost: ₹${Math.round(o.monthly_cost||0)}</div>
          <div style="opacity:.8;">Slots: ${o.slots_filled}/${o.slots_total} • ${o.status}</div>
          <div style="display:flex;gap:6px;margin-top:6px;">
            <button class="fa-donate" data-type="sponsorship" data-orphan="${o.id}" data-amount="${o.monthly_cost||0}" style="flex:1;padding:.6rem 1rem;border-radius:8px;border:1px solid #111;background:#111;color:#fff;">Sponsor Now</button>
            <button class="fa-donate" data-type="general" data-amount="${o.monthly_cost||0}" style="padding:.6rem .9rem;border-radius:8px;border:1px solid #e5e7eb;background:#f9fafb;">Donate</button>
          </div>
        `;
        el.appendChild(card);
      });

      el.addEventListener('click', async (e)=>{
        const b = e.target.closest('.fa-donate'); if (!b) return;
        const type = b.getAttribute('data-type');
        const orphan_id = b.getAttribute('data-orphan') || null;
        const amount = prompt('Enter amount (INR)', Math.round(b.getAttribute('data-amount')||0)) || '';
        if (!amount || isNaN(parseFloat(amount))) return;

        const email = prompt('Your email')||'';
        if (!email) return;
        const name = prompt('Your name (optional)')||'';
        const phone = prompt('Phone (optional)')||'';

        const r = await fetch(root + '/checkout/order', {
          method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({ amount: parseFloat(amount), currency:'INR', type, orphan_id, email, name, phone })
        });
        const j = await r.json();
        if (!j.ok) { alert(j.message || 'Error'); return; }

          const options = {
            key: j.key_id,
            order_id: j.order.id,
            name: document.title || 'Future Achievers',
            prefill: { email: email, name: name, contact: phone },
            handler: function (resp) {
              fetch(root + '/checkout/verify', {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify({
                  order_id: resp.razorpay_order_id,
                  payment_id: resp.razorpay_payment_id,
                  razorpay_signature: resp.razorpay_signature
                })
              })
                .then(r=>r.json())
                .then(v=>{
                  if (v.ok) {
                    if (window.fa_donor_receipts_url) {
                      window.location.href = window.fa_donor_receipts_url;
                    }
                  } else {
                    alert(v.message || 'Payment verification failed');
                  }
                })
                .catch(()=>alert('Payment verification failed'));
            }
          };
        const rz = new window.Razorpay(options);
        rz.open();
      });
    })
}
