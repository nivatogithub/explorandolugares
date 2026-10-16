(()=>{'use strict';
const root=document.querySelector('[data-plan-prices]');if(!root)return;
let prices={},requirements={};try{prices=JSON.parse(root.dataset.planPrices||'{}');requirements=JSON.parse(root.dataset.planRequirements||'{}')}catch{return}
const format=(amount,currency)=>new Intl.NumberFormat('es-CO',{style:'currency',currency:currency||'COP',maximumFractionDigits:0}).format(Number(amount));
document.querySelectorAll('.account-payment-form').forEach(form=>{
 const plan=form.querySelector('[name="plan_code"]'),frequency=form.querySelector('[name="billing_frequency"]'),proof=form.querySelector('[name="payment_proof"]'),price=form.querySelector('[data-price-output]'),detail=form.querySelector('[data-plan-requirements-output]'),frequencyLabel=frequency.closest('label');
 const update=()=>{const code=plan.value,free=code==='free',option=prices[code]?.[frequency.value],value=free?'Sin costo':option?.amount==null?'Tarifa no configurada':format(option.amount,option.currency);price.textContent=value;frequencyLabel.hidden=free;proof.required=!free;detail.textContent=(requirements[code]||[]).join(' ')};
 const syncFrequencies=()=>{const available=Object.entries(prices[plan.value]||{}).filter(([,item])=>Number(item?.amount)>0).map(([key])=>key);for(const option of frequency.options){const enabled=available.includes(option.value);option.disabled=!enabled;option.hidden=!enabled}if(available.length&&!available.includes(frequency.value))frequency.value=available[0];};
 plan.addEventListener('change',()=>{syncFrequencies();update()});frequency.addEventListener('change',update);syncFrequencies();update();
});
})();
