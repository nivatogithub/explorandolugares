(()=>{'use strict';
const root=document.querySelector('[data-plan-prices]');if(!root)return;
let prices={},requirements={},operatorPrices={};try{prices=JSON.parse(root.dataset.planPrices||'{}');requirements=JSON.parse(root.dataset.planRequirements||'{}');operatorPrices=JSON.parse(root.dataset.operatorPrices||'{}')}catch{return}
const format=(amount,currency)=>new Intl.NumberFormat('es-CO',{style:'currency',currency:currency||'COP',maximumFractionDigits:0}).format(Number(amount));
document.querySelectorAll('.account-payment-form').forEach(form=>{
 const plan=form.querySelector('[name="plan_code"]'),frequency=form.querySelector('[name="billing_frequency"]'),proof=form.querySelector('[name="payment_proof"]'),price=form.querySelector('[data-price-output]'),detail=form.querySelector('[data-plan-requirements-output]'),frequencyLabel=frequency.closest('label'),submit=form.querySelector('[type="submit"]');
 const update=()=>{
  const code=plan.value,free=code==='free',option=prices[code]?.[frequency.value],baseQuotable=option?.amount!=null&&Number.isFinite(Number(option.amount))&&Number(option.amount)>0;
  const operator=form.querySelector('[data-operator-experience]'),operatorChoice=form.querySelector('[data-operator-choice]');if(operator){if(free)operator.value='0';operator.disabled=free;}if(operatorChoice)operatorChoice.hidden=free;
  const hasOperator=operator&&Number(operator.value)>0,extra=hasOperator?operatorPrices[frequency.value]:null,quotable=baseQuotable&&(!hasOperator||(extra&&Number(extra.amount)>0)),total=Number(option?.amount||0)+Number(extra?.amount||0);
  price.textContent=free?'Sin costo':quotable?format(total,option.currency):'Precio pendiente de cotización. No realices pagos todavía.';
  const instructions=form.querySelector('[data-payment-instructions]');if(instructions)instructions.hidden=free;
  const transaction=form.querySelector('[data-payment-transaction]');if(transaction){transaction.hidden=free;transaction.querySelectorAll('input,select').forEach(field=>{field.disabled=free;field.required=!free;});}
  frequencyLabel.hidden=free;frequency.disabled=free;proof.required=!free&&quotable;proof.disabled=free||!quotable;
  if(proof.closest('label'))proof.closest('label').hidden=free;
  if(submit){submit.disabled=!free&&!quotable;submit.textContent=free?'Guardar suscripción gratuita':'Enviar suscripción y soporte';}
  detail.textContent=(extra?'Suscripción '+format(option.amount,option.currency)+' + operador '+format(extra.amount,extra.currency)+'. ':'')+(requirements[code]||[]).join(' ');
 };
 form.querySelector('[data-operator-experience]')?.addEventListener('change',update);plan.addEventListener('change',update);frequency.addEventListener('change',update);update();
});
})();
