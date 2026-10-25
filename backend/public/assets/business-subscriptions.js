(() => {
  'use strict';
  // Prices, chosen subscription and eligibility are resolved by the payment route.
  document.querySelectorAll('.account-payment-form').forEach(form=>{
    form.addEventListener('submit',()=>{
      const button=form.querySelector('button[type="submit"]');
      if(button){button.disabled=true;button.textContent='Enviando comprobante…';}
    });
  });
})();
