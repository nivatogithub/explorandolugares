(async()=>{
 const form=document.getElementById('business-registration');if(!form)return;
 const source=new URL('contact-options.json',document.currentScript.src),category=form.elements.category,town=form.elements.municipality,department=form.elements.contact_department;
 const lang=document.documentElement.lang,locale=lang==='en'?'en':lang==='pt-BR'?'pt':'es';
 const words={es:['Selecciona un departamento','Selecciona tu municipio','Selecciona una opción'],en:['Select a department','Select your municipality','Select an option'],pt:['Selecione um departamento','Selecione seu município','Selecione uma opção']}[locale];
 const option=(value,label)=>{const node=document.createElement('option');node.value=value;node.textContent=label;return node;};
 const title=s=>s.toLocaleLowerCase('es-CO').replace(/(^|\s)\p{L}/gu,x=>x.toLocaleUpperCase('es-CO'));
 const normal=s=>s.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]/g,'');
 const requested=new URL(document.URL).searchParams.get('municipio');
 try{
  const response=await fetch(source,{cache:'no-cache'});if(!response.ok)throw Error();const data=await response.json();
  if(!Array.isArray(data.nationalMunicipalities)||!data.departments||!Array.isArray(data.groups))throw Error();
  const selected=category.value;const fragment=document.createDocumentFragment();fragment.append(option('',words[2]));
  for(const group of data.groups){const node=document.createElement('optgroup');node.label=locale==='es'?group.label:group[locale];for(const item of group.categories)node.append(option(item.value,locale==='es'?item.label:item[locale]));fragment.append(node);}category.replaceChildren(fragment);category.value=selected;
  const previousDepartment=department.value,previousTown=town.value;department.replaceChildren(option('',words[0]));
  Object.entries(data.departments).sort((a,b)=>a[1].localeCompare(b[1],'es')).forEach(([code,name])=>department.append(option(code,title(name))));department.value=previousDepartment;
  const aliases={bogota:'11001',cartagena:'13001',jerico:'05368',medellin:'05001',santamarta:'47001'};const requestedCode=aliases[normal(requested||'')]||requested;
  const cities=data.nationalMunicipalities;const matches=requestedCode?cities.filter(row=>/^[0-9]{5}$/.test(requestedCode)?row.code===requestedCode:normal(row.name)===normal(requestedCode)):[];
  const match=matches.length===1?matches[0]:null;if(!department.value&&match)department.value=match.department_code;
  const refresh=()=>{const before=town.value;town.replaceChildren(option('',words[1]));cities.filter(row=>row.department_code===department.value).sort((a,b)=>a.name.localeCompare(b.name,'es')).forEach(row=>{const item=option(row.code,row.code==='11001'?'Bogotá D. C.':title(row.name));item.dataset.landlinePrefix=data.departmentPrefixes[row.department_code];town.append(item);});town.value=[...town.options].some(o=>o.value===before)?before:'';town.dispatchEvent(new Event('change',{bubbles:true}));town.dispatchEvent(new Event('floating-select:refresh'));};
  department.addEventListener('change',refresh);refresh();if(match&&department.value===match.department_code)town.value=match.code;else if([...town.options].some(o=>o.value===previousTown))town.value=previousTown;
  town.dispatchEvent(new Event('change',{bubbles:true}));[department,town,category].forEach(node=>node.dispatchEvent(new Event('floating-select:refresh')));
 }catch{ // Static national options remain available if the JSON cannot be loaded.
 }
})();
