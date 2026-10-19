(()=>{
    const root=document.querySelector('[data-plan-requirements][data-plan-prices]');
    if(!root)return;
    let requirements={},prices={};
    try{requirements=JSON.parse(root.dataset.planRequirements||'{}');prices=JSON.parse(root.dataset.planPrices||'{}');}catch{return;}
    const plan=document.getElementById('subscription-plan');
    const frequency=document.getElementById('subscription-frequency');
    const frequencyField=document.getElementById('frequency-field');
    const priceBox=document.getElementById('subscription-price');
    const requirementsBox=document.getElementById('tier-requirements');
    let request=0;
    const business=root.querySelector('select[name="business_id"]');
    const update=async()=>{
        const sequence=++request;
        const code=plan.value;
        const available=Object.entries(prices[code]||{}).filter(([,item])=>Number(item?.amount)>0).map(([key])=>key);
        for(const option of frequency.options){const enabled=available.includes(option.value);option.disabled=!enabled;option.hidden=!enabled;}
        if(available.length&&!available.includes(frequency.value))frequency.value=available[0];
        const amount=prices[code]?.[frequency.value]?.amount;
        const currency=prices[code]?.[frequency.value]?.currency||'COP';
        frequencyField.hidden=code==='free'||!code;
        if(code==='free')priceBox.textContent='Gratis: no genera cobro.';
        else if(!code)priceBox.textContent='Selecciona una suscripción y frecuencia para consultar su tarifa.';
        else if(amount==null)priceBox.textContent='La tarifa de esta combinación aún no está configurada en la tabla de precios; no se puede confirmar un pago.';
        else priceBox.textContent='Importe para '+frequency.options[frequency.selectedIndex].text+': '+new Intl.NumberFormat('es-CO',{style:'currency',currency,maximumFractionDigits:0}).format(amount)+'. Este importe queda guardado como referencia de esta elección.';
        if(code&&business?.value){
            const url=new URL(location.href);url.searchParams.set('subscription_quote','1');url.searchParams.set('business_id',business.value);url.searchParams.set('plan_code',code);url.searchParams.set('billing_frequency',code==='free'?'':frequency.value);
            try{const response=await fetch(url,{cache:'no-store',credentials:'same-origin'}),data=await response.json();if(sequence!==request)return;if(!response.ok)throw new Error(data.error||'No se pudo cotizar.');const q=data.quote,format=c=>new Intl.NumberFormat('es-CO',{style:'currency',currency:q.currency,maximumFractionDigits:2}).format(c/100);
                if(q.kind==='upgrade')priceBox.textContent='Upgrade: '+format(q.target_remaining_cents)+' por el tiempo restante − '+format(q.credit_cents)+' de saldo = '+format(q.due_cents)+' a pagar. Conserva vencimiento '+q.ends_at+' UTC. No activar antes de validar el pago.';
                else if(q.kind==='renewal')priceBox.textContent='Cambio para el vencimiento '+q.effective_at+' UTC. Importe del siguiente período: '+format(q.due_cents)+'. Mantener los beneficios actuales hasta entonces.';
            }catch(error){if(sequence===request)priceBox.textContent=error.message;}
        }
        requirementsBox.replaceChildren();
        if(!code){requirementsBox.hidden=true;return;}
        requirementsBox.hidden=false;
        const title=document.createElement('strong');
        title.textContent=(plan.options[plan.selectedIndex]?.text||'Suscripción')+' · información y medios';
        requirementsBox.append(title);
        const list=document.createElement('ul');
        for(const value of requirements[code]||[]){const item=document.createElement('li');item.textContent=value;list.append(item);}
        requirementsBox.append(list);
    };
    business?.addEventListener('change',update);
    plan.addEventListener('change',update);
    frequency.addEventListener('change',update);
    root.querySelector('.subscription-publish-form')?.addEventListener('submit',event=>{
        if(!window.confirm('¿Confirmas que el negocio ya superó la revisión y debe hacerse público hoy? Un upgrade conserva su vencimiento y exige pago validado; una bajada espera al vencimiento.'))event.preventDefault();
    });
    update();
})();
