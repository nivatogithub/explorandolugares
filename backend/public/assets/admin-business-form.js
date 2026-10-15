(()=>{
 const root=document.querySelector('[data-plans]'),form=document.getElementById('business-form');if(!root||!form)return;
 let plans={};try{plans=JSON.parse(root.dataset.plans||'{}')}catch{return}
 const plan=document.getElementById('business-plan'),frequency=document.getElementById('business-frequency'),frequencyField=document.getElementById('business-frequency-field'),quote=document.getElementById('business-price-quote'),details=document.getElementById('business-plan-details'),images=document.getElementById('business-reference-images'),imageGuidance=document.getElementById('business-image-guidance');
 const money=(price)=>new Intl.NumberFormat('es-CO',{style:'currency',currency:price.currency||'COP',maximumFractionDigits:0}).format(price.amount);
 const update=()=>{
   const selected=plans[plan.value]||plans.free||{},features=new Set(selected.features||[]),isFree=plan.value==='free';
   for(const field of form.querySelectorAll('[data-plan-feature]')){const enabled=features.has(field.dataset.planFeature);field.hidden=!enabled;field.querySelectorAll('input,select,textarea').forEach(input=>input.disabled=!enabled);}
   const requiredFields={
     'translations[es][summary]':features.has('expanded_details'),
     'translations[es][description]':features.has('extended_description'),
     'translations[es][brand_story]':features.has('brand_presentation'),
     'translations[es][featured_services]':features.has('brand_presentation')
   };
   for(const [name,required] of Object.entries(requiredFields)){const input=form.elements.namedItem(name);if(input)input.required=required;}
   const textLimits=selected.text_limits||{};
   for(const field of form.querySelectorAll('[data-plan-text]')){
     const limit=Number(textLimits[field.dataset.planText]||0),counter=field.closest('label')?.querySelector('[data-text-counter]');
     field.maxLength=limit||10000;
     if(counter){counter.hidden=!limit;counter.textContent=limit?`${Array.from(field.value).length} / ${limit} caracteres`:'';}
     if(!field.dataset.counterBound){field.addEventListener('input',()=>{const currentPlan=plans[plan.value]||{},currentLimit=Number(currentPlan.text_limits?.[field.dataset.planText]||0),currentCounter=field.closest('label')?.querySelector('[data-text-counter]');if(currentCounter&&currentLimit)currentCounter.textContent=`${Array.from(field.value).length} / ${currentLimit} caracteres`;});field.dataset.counterBound='1';}
   }
   frequencyField.hidden=isFree;frequency.disabled=isFree;frequency.required=!isFree;
   const price=selected.prices?.[frequency.value];
   quote.textContent=isFree?'Gratis: aparecerá en el directorio con nombre, categoría y ubicación. Los datos de contacto se conservan para revisión y no se publican.':price?.amount?`${selected.name}: ${money(price)} por periodo ${frequency.options[frequency.selectedIndex]?.text.toLowerCase()}. La vigencia empieza al publicar.`:`${selected.name||'Suscripción'}: no hay una tarifa configurada para esta frecuencia. El administrador debe definirla antes de guardar esta elección.`;
   const req=(selected.requirements||[]).join(' ');quote.dataset.requirements=req;
   if(details){details.replaceChildren();(selected.requirements||[]).forEach(item=>{const p=document.createElement('p');p.textContent=item;details.append(p);});}
   const refCaps={featured:1,page:2,page_premium:4},limit=refCaps[plan.value]||0;
   if(images){images.disabled=!features.has('business_logo');images.max=limit;}
   if(imageGuidance){imageGuidance.textContent=limit===0?'Este nivel no pide imágenes. Si no tiene logo, se usan sus iniciales.':plan.value==='featured'?'Hasta 1 referencia privada: logo. Si no tiene, la tarjeta usará sus iniciales.':plan.value==='page'?'Hasta 2 referencias privadas: logo y una imagen. Se preparan para el borrador y no se publican directamente.':'Hasta 4 referencias privadas: logo y material para una galería final de hasta 3 fotos. El equipo crea y revisa las imágenes antes de publicarlas.';}
   if(images)images.setAttribute('data-tier-limit',String(limit));
 };
 plan.addEventListener('change',update);frequency.addEventListener('change',update);form.addEventListener('submit',event=>{const selected=plans[plan.value]||{};if(plan.value!=='free'&&!(selected.prices?.[frequency.value]?.amount>0)){event.preventDefault();quote.textContent='No se guardó: primero hay que configurar la tarifa de esta suscripción y frecuencia.';quote.focus();}});update();
 if(images)images.addEventListener('change',()=>{const limit=Number(images.dataset.tierLimit||0);if(images.files.length>limit){alert(`Este nivel admite hasta ${limit} archivos de referencia. Se limpió la selección para que elijas la cantidad correcta.`);images.value='';}});
})();
