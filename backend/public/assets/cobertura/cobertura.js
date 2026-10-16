(() => {
  'use strict';
  const labels={published:'Publicado',in_progress:'En preparación',pending:'Pendiente',unknown:'Sin confirmación',untracked:'Pendiente de incorporar'};
  const $=id=>document.getElementById(id), root=new URL('../../',location.href);
  const el=(tag,text,className)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(className)n.className=className;return n;};
  const normal=s=>s.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
  let data=null,department='',municipality='',filter='all',mapVersion=0,activeTab='cobertura';const maps=new Map();
  const current=()=>data.departments.find(d=>d.code===department);
  async function json(url){const r=await fetch(url,{credentials:'omit',headers:{Accept:'application/json'}});if(!r.ok)throw Error('No disponible');return r.json();}
  async function geometry(file){if(!maps.has(file))maps.set(file,json(new URL('assets/cobertura/'+file,root)).catch(e=>{maps.delete(file);throw e;}));return maps.get(file);}
  const option=(value,text)=>{const o=el('option',text);o.value=value;return o;};
  function updateUrl(){const url=new URL(location.href);if(data){url.search='';if(department)url.searchParams.set('departamento',department);if(municipality)url.searchParams.set('municipio',municipality);}url.hash=activeTab;history.replaceState(null,'',url);}
  function setDepartment(code){department=data.departments.some(d=>d.code===code)?code:'';municipality='';$('department').value=department;$('department').dispatchEvent(new Event('floating-select:refresh'));updateUrl();renderDetail();renderMap();}
  function setMunicipality(code){municipality=current()?.municipalities.some(m=>m.code===code)?code:'';$('municipality').value=municipality;$('municipality').dispatchEvent(new Event('floating-select:refresh'));updateUrl();renderPlaces();highlight();renderAudience();}
  function renderDetail(){
    const d=current();$('country-button').hidden=!d;$('map-title').textContent=d?d.name:'Colombia';$('map-kicker').textContent=d?'ACÉRCATE A SUS MUNICIPIOS':'ELIGE POR DÓNDE EMPEZAR';
    $('detail-title').textContent=d?'Estado de las guías':'Guías para explorar';$('detail-description').textContent=d?'Estas son las guías del departamento que forman parte de nuestro catálogo.':'Selecciona un departamento o busca entre los destinos del catálogo nacional.';
    $('department-summary').hidden=!d;$('municipality').disabled=!d;$('municipality').replaceChildren(option('','Todos los municipios'));
    if(d){$('department-percent').textContent='Ampliación progresiva';
      $('department-count').textContent=(data.publication_source_available?`${d.counts.published} ${d.counts.published===1?'guía publicada':'guías publicadas'}`:'Publicación sin confirmar')+` · ${d.counts.in_progress} en preparación identificadas. El listado de trabajo aún no es el alcance completo del departamento.`;
      d.municipalities.slice().sort((a,b)=>a.name.localeCompare(b.name,'es')).forEach(m=>$('municipality').append(option(m.code,m.name)));
    }
    renderPlaces();renderAudience();
  }
  function renderPlaces(){
    const d=current(), query=normal($('search').value.trim());let rows=(d?[d]:data.departments).flatMap(dep=>dep.places.map(p=>({...p,department_name:dep.name})));
    rows=rows.filter(p=>(!municipality||p.municipality_code===municipality)&&(filter==='all'||p.status===filter)&&(!query||normal(p.name+' '+p.department_name).includes(query)));
    rows.sort((a,b)=>({published:0,in_progress:1,pending:2,unknown:3}[a.status]-{published:0,in_progress:1,pending:2,unknown:3}[b.status])||a.name.localeCompare(b.name,'es'));
    $('result-count').textContent=`${rows.length} ${rows.length===1?'destino':'destinos'} en esta selección`;
    $('places').replaceChildren();
    if(!rows.length)$('places').append(el('p',municipality?'No hay destinos del catálogo que coincidan con esta selección.':'No hay destinos que coincidan con estos filtros.','empty'));
    rows.forEach(p=>{const article=el('article',undefined,'place'),top=el('div',undefined,'place-top');top.append(el('h3',p.name),el('span',labels[p.status],'badge '+p.status));article.append(top);
      const parent=data.departments.flatMap(x=>x.places).find(x=>x.id===p.parent_place_id);article.append(el('p',parent?'Destino dentro de '+parent.name:(d?'Guía del destino':p.department_name)));
      if(p.url&&p.status==='published'){const a=el('a','Explorar la guía ↗');a.href=new URL(p.url.replace(/^\//,''),root).href;article.append(a);} $('places').append(article);
    });
    const m=d?.municipalities.find(x=>x.code===municipality);$('municipality-summary').hidden=!m;
    if(m)$('municipality-summary').textContent=!data.publication_source_available?'Publicación sin confirmar para '+m.name+'.':m.tracked_count?`${m.name}: ${m.published_count} de ${m.tracked_count} destinos del catálogo con guía publicada. ${labels[m.status]}.`:`${m.name} aparece en el mapa, pero aún no tiene destinos incorporados a este catálogo.`;
    if(d&&municipality)$('map-hint').textContent=m.name+' · '+labels[m.status];
  }
  function highlight(){document.querySelectorAll('#map path[data-code]').forEach(p=>{const selected=p.dataset.code===municipality;p.classList.toggle('selected',selected);p.setAttribute('aria-pressed',String(selected));});}
  function featureState(code){const d=current();if(d)return d.municipalities.find(m=>m.code===code)?.status||'untracked';const dep=data.departments.find(x=>x.code===code);if(!dep)return 'untracked';return dep.counts.published?'published':dep.counts.in_progress?'in_progress':data.publication_source_available?'pending':'unknown';}
  async function renderMap(){
    const version=++mapVersion,d=current();$('map').setAttribute('aria-busy','true');$('map').replaceChildren(el('p','Cargando mapa…'));
    $('map-hint').textContent=d?'Selecciona un municipio en el mapa o en la lista.':'Selecciona un departamento en el mapa o en la lista.';
    try{
      const geo=await geometry(d?'municipios-'+d.code+'.geojson':'departamentos.geojson');if(version!==mapVersion)return;
      const ns='http://www.w3.org/2000/svg';const svg=document.createElementNS(ns,'svg');svg.setAttribute('role','group');svg.setAttribute('aria-label',d?'Municipios de '+d.name:'Departamentos de Colombia');
      const defs=document.createElementNS(ns,'defs');defs.innerHTML='<pattern id="unknown-pattern" width="6" height="6" patternUnits="userSpaceOnUse"><rect width="6" height="6" fill="#dce3e5"/><path d="M0 0L6 6" stroke="#91a1a5" stroke-width="2"/></pattern>';svg.append(defs);
      const points=[];function walk(c){if(typeof c[0]==='number')points.push(c);else c.forEach(walk);}geo.features.forEach(f=>walk(f.geometry.coordinates));
      const xs=points.map(p=>p[0]),ys=points.map(p=>-p[1]);const minX=Math.min(...xs),maxX=Math.max(...xs),minY=Math.min(...ys),maxY=Math.max(...ys),w=maxX-minX,h=maxY-minY,pad=Math.max(w,h)*.035;
      svg.setAttribute('viewBox',`${minX-pad} ${minY-pad} ${w+pad*2} ${h+pad*2}`);
      geo.features.forEach(f=>{const code=f.properties.code,name=f.properties.name,state=featureState(code),path=document.createElementNS(ns,'path');const polygons=f.geometry.type==='Polygon'?[f.geometry.coordinates]:f.geometry.coordinates;
        const commands=polygons.flatMap(p=>p.map(r=>r.map((v,i)=>(i?'L':'M')+v[0]+' '+(-v[1])).join(' ')+'Z')).join(' ');
        path.setAttribute('d',commands);path.setAttribute('fill-rule','evenodd');path.setAttribute('class',state);path.dataset.code=code;path.setAttribute('tabindex','0');path.setAttribute('role','button');path.setAttribute('aria-label',name+' · '+labels[state]);path.setAttribute('aria-pressed','false');
        const title=document.createElementNS(ns,'title');title.textContent=name+' · '+labels[state];path.append(title);
        const choose=()=>{d?setMunicipality(code):setDepartment(code);window.elMeasure?.('map_select',{department,...(municipality?{municipality}:{})});};path.addEventListener('click',choose);path.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();choose();}});path.addEventListener('pointerenter',()=>{$('map-hint').textContent=name+' · '+labels[state];});path.addEventListener('focus',()=>{$('map-hint').textContent=name+' · '+labels[state];});svg.append(path);
      });
      $('map').replaceChildren(svg);highlight();
    }catch(e){if(version===mapVersion)$('map').replaceChildren(el('p','El mapa no pudo cargarse. Puedes consultar la misma información con las listas de departamentos y municipios.','map-error'));}
    finally{if(version===mapVersion)$('map').setAttribute('aria-busy','false');}
  }
  let audienceVersion=0;
  async function renderAudience(){
    const version=++audienceVersion,d=current(),m=d?.municipalities.find(x=>x.code===municipality),days=$('audience-period').value;
    $('audience-scope').textContent=(m?'Interés por '+m.name:d?'Interés por '+d.name:'Audiencia del portal')+' · últimos '+days+' días. Viajeros registrados: comunidad general del portal.';
    const fields={'anonymous_sessions':'anonymous','estimated_visitors':'visitors','registered_travelers':'registered','active_travelers':'active','business_views':'business-views','contact_clicks':'contact'};
    Object.values(fields).forEach(id=>$('audience-'+id).textContent='—');
    $('audience-status').textContent='Consultando medición…';
    try{const url=new URL('api/cobertura.php',root);url.searchParams.set('days',days);if(department)url.searchParams.set('department',department);if(municipality)url.searchParams.set('municipality',municipality);
      const a=(await json(url)).audience;if(version!==audienceVersion)return;
      if(!a||a.status==='not_configured')throw Error('pending');
      Object.entries(fields).forEach(([metric,id])=>{$('audience-'+id).textContent=a.metrics[metric]===null?'—':Number(a.metrics[metric]).toLocaleString('es-CO');});
      $('audience-status').textContent=(a.status==='collecting_baseline'?'Estamos reuniendo una base suficiente; ocultamos grupos pequeños para proteger la privacidad. ':'Medición activa. ')+ 'Solo incluye navegación con permiso. Inicio: '+a.started_at.slice(0,10)+'. Actualización: '+new Date(a.updated_at).toLocaleString('es-CO')+'.';
      $('audience-countries').replaceChildren();const region=new Intl.DisplayNames(['es'],{type:'region'});
      if(a.countries?.length)a.countries.forEach(c=>$('audience-countries').append(el('p',(c.code==='ZZ'?'País no disponible':c.code==='OTHER'?'Otros países (grupos pequeños)':region.of(c.code))+': '+c.count+' vistas de páginas')));else $('audience-countries').textContent='Todavía no hay un desglose publicable por país.';
      const attribution=el('a','País aproximado: DB-IP Country Lite');attribution.href='https://db-ip.com/';attribution.rel='noopener noreferrer';$('audience-countries').append(attribution);
      $('audience-trend').replaceChildren(el('p',a.page_views===null?'Histórico pendiente de una base suficiente.':a.page_views+' vistas de páginas en el periodo.'));
      $('audience-trend').append(el('p',a.comparison_available?(a.growth_percent===null?'Periodo anterior: '+a.previous_views+' vistas. Aún no procede calcular un porcentaje.':'Cambio frente al periodo anterior: '+a.growth_percent+'%.'):'La comparación se habilita cuando existan dos periodos completos medidos.'));
      const sources={direct_unknown:'Acceso directo u origen no identificable',search:'Buscadores',instagram:'Instagram',facebook:'Facebook',referral:'Otros sitios',campaign:'Campañas identificadas'};
      const origin=el('details');origin.append(el('summary','Cómo llegan al portal'));Object.entries(a.sources||{}).forEach(([key,value])=>origin.append(el('p',(sources[key]||key)+': '+value+' vistas')));$('audience-trend').append(origin);
      if(a.trend?.length){const details=el('details'),summary=el('summary','Ver vistas por día');details.append(summary);a.trend.forEach(day=>details.append(el('p',day.day+': '+day.views)));$('audience-trend').append(details);}
    }catch{if(version!==audienceVersion)return;$('audience-status').textContent='Medición todavía no disponible. No mostramos cifras inventadas.';$('audience-countries').textContent='Sin desglose publicable.';$('audience-trend').textContent='Sin histórico comprobado.';}
  }
  // Invitations are composed locally. No contacts are collected and no message is sent automatically.
  const invitations={
    people:{title:'Invitación a familiares y conocidos',message:'¡Te invito a conocer Explorando Lugares! Estamos conectando viajeros, municipios y negocios locales de Colombia. Descubre la plataforma y compártela con familiares o conocidos que quieran explorar el país o aportar conocimiento sobre su municipio.\n\nhttps://explorandolugares.com/'},
    business:{title:'Invitación a un comercio',message:'¡Pensé en tu negocio al conocer Explorando Lugares! Es una plataforma para descubrir destinos de Colombia y dar visibilidad a su economía local. Puedes consultar cómo participar con una ficha básica gratuita y conocer opciones adicionales de pauta para darte a conocer entre viajeros.\n\nConoce la plataforma: https://explorandolugares.com/\nConsulta cómo participar: https://explorandolugares.com/negocios-locales/interes/'}
  };
  let inviteTrigger=null;
  function refreshInvitation(){ $('invite-whatsapp').href='https://wa.me/?text='+encodeURIComponent($('invite-message').value); }
  document.querySelectorAll('[data-invite]').forEach(button=>button.addEventListener('click',()=>{
    window.elMeasure?.('invitation_open',{channel:button.dataset.invite});inviteTrigger=button;const invitation=invitations[button.dataset.invite];$('invite-preview-title').textContent=invitation.title;$('invite-message').value=invitation.message;refreshInvitation();
    $('invite-preview').hidden=false;$('invite-native').hidden=typeof navigator.share!=='function';$('invite-status').textContent='Tú eliges a quién enviarlo. No se envían mensajes automáticamente.';
    document.querySelectorAll('[data-invite]').forEach(b=>b.setAttribute('aria-expanded',String(b===button)));$('invite-message').focus({preventScroll:true});
  }));
  $('invite-message').addEventListener('input',refreshInvitation);
  $('invite-close').addEventListener('click',()=>{$('invite-preview').hidden=true;document.querySelectorAll('[data-invite]').forEach(b=>b.setAttribute('aria-expanded','false'));inviteTrigger?.focus();});
  $('invite-copy').addEventListener('click',async()=>{try{await navigator.clipboard.writeText($('invite-message').value);$('invite-status').textContent='Invitación copiada. Pégala donde quieras compartirla.';}catch(e){$('invite-message').focus();$('invite-message').select();$('invite-status').textContent='Selecciona y copia el mensaje para compartirlo.';}});
  $('invite-native').addEventListener('click',async()=>{try{await navigator.share({title:'Explorando Lugares',text:$('invite-message').value});$('invite-status').textContent='Opciones de compartir cerradas. No podemos confirmar la entrega del mensaje.';}catch(e){$('invite-status').textContent=e.name==='AbortError'?'Puedes elegir otra opción cuando quieras.':'No se pudo abrir el menú. Puedes copiar la invitación o usar WhatsApp.';}});
  const pageTabs=[...document.querySelectorAll('[data-page-tab]')];
  function tabFromHash(){const h=location.hash.slice(1);return ['como-medimos','invitar'].includes(h)?'participar':h==='audiencia'?'audiencia':h==='participar'?'participar':'cobertura';}
  function activateTab(id,writeUrl=true){
    activeTab=id;pageTabs.forEach(button=>{const selected=button.dataset.pageTab===id;button.setAttribute('aria-selected',String(selected));button.tabIndex=selected?0:-1;$(button.getAttribute('aria-controls')).hidden=!selected;});
    // Close floating menus when their panel is hidden; preserve all selected values.
    document.querySelectorAll('[role="tabpanel"][hidden] .floating-select-trigger[aria-expanded="true"]').forEach(button=>button.click());
    if(writeUrl)updateUrl();
  }
  document.querySelector('.coverage-tabs').hidden=false;activateTab(tabFromHash(),false);
  pageTabs.forEach((button,index)=>{
    button.addEventListener('click',()=>activateTab(button.dataset.pageTab));
    button.addEventListener('keydown',event=>{let target;
      if(event.key==='ArrowRight')target=(index+1)%pageTabs.length;else if(event.key==='ArrowLeft')target=(index-1+pageTabs.length)%pageTabs.length;else if(event.key==='Home')target=0;else if(event.key==='End')target=pageTabs.length-1;else return;
      event.preventDefault();activateTab(pageTabs[target].dataset.pageTab);pageTabs[target].focus();
    });
  });
  window.addEventListener('hashchange',()=>{activateTab(tabFromHash(),false);if(['#como-medimos','#invitar'].includes(location.hash))$(location.hash.slice(1)).scrollIntoView({block:'start'});});
  async function init(){
    try{
      data=await json(new URL('api/cobertura.php',root));if(!Array.isArray(data.departments)||!data.country)throw Error('Respuesta inválida');
      $('country-published').textContent=data.publication_source_available?data.country.counts.published:'—';$('country-total').textContent=data.publication_source_available?'Guías públicas de Colombia consultadas en el portal':'Publicación temporalmente sin confirmar';$('catalog-scope').textContent='Nuestra meta es cubrir todos los municipios de Colombia y sus destinos turísticos. Las guías se incorporan progresivamente, por grupos de prioridad y después de revisar su contenido.';$('country-progress').textContent=data.country.counts.in_progress;
      if(!data.publication_source_available){$('notice').hidden=false;$('notice').textContent='No podemos confirmar ahora cuántas guías están publicadas. Mostramos sin dato, no un cero.';}
      $('updated').textContent='Estado consultado: '+new Intl.DateTimeFormat('es-CO',{dateStyle:'medium',timeStyle:'short',timeZone:'America/Bogota'}).format(new Date(data.checked_at))+' (Colombia). Catálogo: '+data.universe_version+'.';
      data.departments.slice().sort((a,b)=>a.name.localeCompare(b.name,'es')).forEach(d=>$('department').append(option(d.code,d.name)));
      $('department').addEventListener('change',e=>{setDepartment(e.target.value);if(e.isTrusted&&department)window.elMeasure?.('map_select',{department});});$('municipality').addEventListener('change',e=>setMunicipality(e.target.value));$('country-button').addEventListener('click',()=>setDepartment(''));$('search').addEventListener('input',renderPlaces);$('audience-period').addEventListener('change',renderAudience);
      document.querySelectorAll('[data-filter]').forEach(b=>b.addEventListener('click',()=>{filter=b.dataset.filter;document.querySelectorAll('[data-filter]').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));renderPlaces();}));
      const params=new URL(location.href).searchParams;const start=params.get('departamento')||'',mun=params.get('municipio')||'';setDepartment(start);if(mun)setMunicipality(mun);
    }catch(e){$('notice').hidden=false;$('notice').textContent='No pudimos cargar la cobertura. Vuelve a intentar más tarde; puedes seguir explorando las guías desde Destinos.';$('map').replaceChildren(el('p','Información temporalmente no disponible.','map-error'));$('map').setAttribute('aria-busy','false');$('updated').textContent='Sin actualización disponible.';}
  }
  init();
})();
