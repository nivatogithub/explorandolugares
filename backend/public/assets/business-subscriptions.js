(()=>{'use strict';
const root=document.querySelector('[data-plan-prices]');if(!root)return;
let prices={},requirements={},operatorPrices={};try{prices=JSON.parse(root.dataset.planPrices||'{}');requirements=JSON.parse(root.dataset.planRequirements||'{}');operatorPrices=JSON.parse(root.dataset.operatorPrices||'{}')}catch{return}
const date=stamp=>new Intl.DateTimeFormat('es-CO',{timeZone:'America/Bogota',year:'numeric',month:'long',day:'numeric'}).format(new Date(stamp.replace(' ','T')+'Z'));
const format=(amount,currency)=>new Intl.NumberFormat('es-CO',{style:'currency',currency:currency||'COP',minimumFractionDigits:0,maximumFractionDigits:2}).format(Number(amount));
document.querySelectorAll('.account-payment-form').forEach(form=>{
 const plan=form.querySelector('[name="plan_code"]'),frequency=form.querySelector('[name="billing_frequency"]'),proof=form.querySelector('[name="payment_proof"]'),price=form.querySelector('[data-price-output]'),detail=form.querySelector('[data-plan-requirements-output]'),frequencyLabel=frequency.closest('label'),submit=form.querySelector('[type="submit"]');
 let request=0,expiry;
 const update=async()=>{
  const sequence=++request;clearTimeout(expiry);form.querySelector('[name="subscription_quote_token"]').value='';
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
  submit.disabled=true;price.textContent='Calculando importe…';
  const url=new URL(location.href);url.searchParams.set('subscription_quote','1');url.searchParams.set('business',form.querySelector('[name="business_id"]').value);url.searchParams.set('plan_code',code);url.searchParams.set('billing_frequency',frequency.value);url.searchParams.set('operator_experience_id',operator?.value||'0');
  try{
   const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}}),data=await response.json();if(sequence!==request)return;
   if(!response.ok||!data.quote)throw new Error(data.error||'No se pudo calcular el importe.');
   const q=data.quote;form.querySelector('[name="subscription_quote_token"]').value=data.token;
   price.textContent=format(q.due_cents/100,q.currency);
   if(q.kind==='upgrade')detail.textContent='Por el tiempo restante: '+format(q.target_remaining_cents/100,q.currency)+' − saldo sin consumir '+format(q.credit_cents/100,q.currency)+' = '+format(q.due_cents/100,q.currency)+' a pagar ahora. Vence el '+date(q.ends_at)+'. Conservas tu suscripción actual hasta validar el pago de la diferencia.';
   else if(q.kind==='renewal')detail.textContent='Cambio para el vencimiento: '+date(q.effective_at)+'. La suscripción vigente conserva sus beneficios hasta entonces. '+(free?'Sin cobro.':'Importe del siguiente período: '+format(q.due_cents/100,q.currency)+'.');
   const paid=form.querySelector('[name="paid_amount"]');if(paid)paid.min=(q.due_cents/100).toFixed(2);
   submit.disabled=false;if(q.kind==='renewal'&&free)submit.textContent='Solicitar Gratis al vencimiento';
   expiry=setTimeout(()=>{submit.disabled=true;price.textContent='Cotización vencida. Cambia la selección para actualizarla antes de pagar.';},1800000);
  }catch(error){if(sequence!==request)return;price.textContent=error.message;submit.disabled=true;}
 };
 form.querySelector('[data-operator-experience]')?.addEventListener('change',update);plan.addEventListener('change',update);frequency.addEventListener('change',update);update();
});
})();
