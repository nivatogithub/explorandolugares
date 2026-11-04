/* Experience selection follows country > department > destination; no purchase is made here. */
(()=>{'use strict';
 const root=document.querySelector('[data-subscription-simulator]');if(!root)return;
 const experience=root.querySelector('[data-sim-experience]');if(!experience)return;
 const en=document.documentElement.lang.startsWith('en'),pt=document.documentElement.lang.startsWith('pt');
 const t=en?['Country','Department / region','Destination','Choose a country','Choose a department','Choose a destination','No add-on','No published experiences for this destination.']:pt?['País','Departamento / região','Destino','Selecione um país','Selecione um departamento','Selecione um destino','Sem complemento','Não há experiências publicadas neste destino.']:['País','Departamento','Lugar','Selecciona un país','Selecciona un departamento','Selecciona un lugar','Sin complemento','No hay experiencias publicadas para este lugar.'];
 const box=document.createElement('div');box.className='simulator-location-filters';const selects=[];
 for(const [i,key] of ['country','department','place'].entries()){const label=document.createElement('label');label.textContent=t[i];const select=document.createElement('select');select.setAttribute('data-sim-'+key,'');select.disabled=true;label.append(select);box.append(label);selects.push(select);}
 experience.closest('label').before(box);const [country,department,place]=selects;
 const status=document.createElement('p');status.className='simulator-location-status';status.setAttribute('role','status');box.after(status);
 let rows=[];const collator=new Intl.Collator(document.documentElement.lang||'es');
 const sorted=xs=>[...new Set(xs.filter(Boolean))].sort(collator.compare);
 const fill=(select,placeholder,items=[])=>{select.replaceChildren(new Option(placeholder,''),...items.map(x=>new Option(x.label,x.value)));};
 const resetExperience=()=>{experience.replaceChildren(new Option(t[6],'0'));status.textContent='';};
 const isFree=()=>root.querySelector('[name=sim_subscription]:checked')?.value==='free';
 function disabled(){const free=isFree();country.disabled=free||!rows.length;department.disabled=free||!country.value;place.disabled=free||!department.value;experience.disabled=free||!place.value;}
 function resetDepartments(){fill(department,t[4],sorted(rows.filter(x=>x.country_code===country.value).map(x=>x.department)).map(x=>({value:x,label:x})));fill(place,t[5]);resetExperience();disabled();}
 function resetPlaces(){const candidates=rows.filter(x=>x.country_code===country.value&&x.department===department.value);const places=[...new Map(candidates.map(x=>[String(x.destination_id),{value:String(x.destination_id),label:x.destination_name}])).values()].sort((a,b)=>collator.compare(a.label,b.label));fill(place,t[5],places);resetExperience();disabled();}
 function filterExperiences(){resetExperience();const selected=rows.filter(x=>x.country_code===country.value&&x.department===department.value&&String(x.destination_id)===place.value);for(const x of selected)experience.add(new Option(x.name,x.id));if(place.value&&!selected.length)status.textContent=t[7];disabled();}
 country.addEventListener('change',resetDepartments);department.addEventListener('change',resetPlaces);place.addEventListener('change',filterExperiences);
 root.addEventListener('change',disabled);
 document.addEventListener('subscription:catalog',event=>{rows=(event.detail.experiences||[]).filter(x=>x.country_code&&x.department&&x.destination_id);const display=new Intl.DisplayNames([document.documentElement.lang||'es'],{type:'region'});fill(country,t[3],sorted(rows.map(x=>x.country_code)).map(code=>({value:code,label:display.of(code)||code})));resetDepartments();});
 fill(country,t[3]);fill(department,t[4]);fill(place,t[5]);resetExperience();disabled();
})();
