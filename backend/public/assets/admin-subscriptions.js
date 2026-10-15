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
    const payment=document.getElementById('payment-reviewed');
    const update=()=>{
        const code=plan.value;
        const amount=prices[code]?.[frequency.value]?.amount;
        const currency=prices[code]?.[frequency.value]?.currency||'COP';
        frequencyField.hidden=code==='free'||!code;
        payment.disabled=code==='free'||amount==null||amount<=0;
        if(code==='free')priceBox.textContent='Gratis: no genera cobro.';
        else if(!code)priceBox.textContent='Selecciona una suscripción y frecuencia para consultar su tarifa.';
        else if(amount==null)priceBox.textContent='La tarifa de esta combinación aún no está configurada en la tabla de precios; no se puede confirmar un pago.';
        else priceBox.textContent='Importe para '+frequency.options[frequency.selectedIndex].text+': '+new Intl.NumberFormat('es-CO',{style:'currency',currency,maximumFractionDigits:0}).format(amount)+'. Este importe queda guardado como referencia de esta elección.';
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
    plan.addEventListener('change',update);
    frequency.addEventListener('change',update);
    root.querySelector('.subscription-publish-form')?.addEventListener('submit',event=>{
        if(!window.confirm('¿Confirmas que el negocio ya superó la revisión y debe hacerse público hoy? La vigencia se contará desde ahora.'))event.preventDefault();
    });
    update();
})();
