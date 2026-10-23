(()=>{'use strict';
const root=document.querySelector('[data-plan-prices]');if(!root)return;
let prices={},requirements={},operatorPrices={};try{prices=JSON.parse(root.dataset.planPrices||'{}');requirements=JSON.parse(root.dataset.planRequirements||'{}');operatorPrices=JSON.parse(root.dataset.operatorPrices||'{}')}catch{return}
const date=stamp=>new Intl.DateTimeFormat('es-CO',{timeZone:'America/Bogota',year:'numeric',month:'long',day:'numeric'}).format(new Date(stamp.replace(' ','T')+'Z'));
const format=(amount,currency)=>new Intl.NumberFormat('es-CO',{style:'currency',currency:currency||'COP',minimumFractionDigits:0,maximumFractionDigits:2}).format(Number(amount));
document.querySelectorAll('.account-payment-form').forEach(form=>{
 const plan=form.querySelector('[name="plan_code"]'),frequency=form.querySelector('[name="billing_frequency"]'),proof=form.querySelector('[name="payment_proof"]'),price=form.querySelector('[data-price-output]'),detail=form.querySelector('[data-plan-requirements-output]'),frequencyLabel=frequency.closest('label'),submit=form.querySelector('[type="submit"]');
 const instructions=form.querySelector('[data-payment-instructions]'),initialPlan=instructions?.dataset.initialPlan||'',initialPeriod=instructions?.dataset.initialPeriod||'';
 if(initialPlan){
  plan.value=initialPlan;if(initialPeriod)frequency.value=initialPeriod;
  const selection=document.createElement('p');selection.className='notice wide';selection.textContent='Suscripción elegida durante el registro: '+(plan.options[plan.selectedIndex]?.textContent||initialPlan)+' · '+(frequency.options[frequency.selectedIndex]?.textContent||initialPeriod)+'.';
  plan.closest('label')?.before(selection);plan.closest('label').hidden=true;frequencyLabel.hidden=true;
 }
 let request=0,expiry;
 const update=async()=>{
  const sequence=++request;clearTimeout(expiry);form.querySelector('[name="subscription_quote_token"]').value='';
  const code=plan.value,free=code==='free',option=prices[code]?.[frequency.value],baseQuotable=option?.amount!=null&&Number.isFinite(Number(option.amount))&&Number(option.amount)>0;
  const operator=form.querySelector('[data-operator-experience]'),operatorChoice=form.querySelector('[data-operator-choice]');if(operator){if(free)operator.value='0';operator.disabled=free;}if(operatorChoice)operatorChoice.hidden=free;
  const hasOperator=operator&&Number(operator.value)>0,extra=hasOperator?operatorPrices[frequency.value]:null,quotable=baseQuotable&&(!hasOperator||(extra&&Number(extra.amount)>0)),total=Number(option?.amount||0)+Number(extra?.amount||0);
  price.textContent=free?'Sin costo':quotable?format(total,option.currency):'Precio pendiente de cotización. No realices pagos todavía.';
  const paymentConfigured=instructions?.dataset.paymentConfigured==='1';if(instructions)instructions.hidden=free;
  const paymentReady=free||paymentConfigured;
  const transaction=form.querySelector('[data-payment-transaction]');if(transaction){transaction.hidden=free||!paymentConfigured;transaction.querySelectorAll('input,select').forEach(field=>{field.disabled=free||!paymentConfigured;field.required=!free&&paymentConfigured;});}
  frequencyLabel.hidden=free||!!initialPlan;frequency.disabled=free;proof.required=!free&&quotable&&paymentConfigured;proof.disabled=free||!quotable||!paymentConfigured;
  if(proof.closest('label'))proof.closest('label').hidden=free||!paymentConfigured;
  if(submit){submit.disabled=!free&&(!quotable||!paymentConfigured);submit.textContent=free?'Guardar suscripción gratuita':paymentConfigured?'Enviar suscripción y soporte':'Esperando medios de pago';}
  detail.textContent=(extra?'Suscripción '+format(option.amount,option.currency)+' + operador '+format(extra.amount,extra.currency)+'. ':'')+(requirements[code]||[]).join(' ');
  if(!paymentReady){price.textContent='El equipo aún no ha publicado un medio de pago. No envíes un comprobante.';detail.textContent='Cuando haya una cuenta, llave Bre-B o código QR configurado, podrás ver la instrucción y adjuntar el comprobante.';return;}
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
document.querySelectorAll('.destination-admin-list').forEach(section=>{
 const title=section.querySelector(':scope > h2');
 if(title?.textContent.trim().startsWith('Visibilidad de este negocio'))section.remove();
});
document.querySelectorAll('a[href*="/business/editorial.php"]').forEach(link=>{
 if(!link.textContent.includes('Editar y revisar'))return;
 const url=new URL(link.href,location.origin);url.searchParams.set('preview','card');
 link.href=url.toString();link.removeAttribute('target');link.removeAttribute('rel');
 link.textContent='Ver mi tarjeta con los datos enviados';
 const copy=link.parentElement?.querySelector('p');
 if(copy)copy.textContent='Revisa la tarjeta privada construida con los datos y fotografías que enviaste. Aún no es pública ni permite editar la solicitud desde esta etapa.';
});
// The owner-facing workflow stops at private preview and payment. Editorial
// preparation happens later by the portal, so this legacy call to edit a draft
// must never appear as an available owner action.
document.querySelector('.account-next-steps')?.remove();
})();
