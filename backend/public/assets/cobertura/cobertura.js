(() => {
  'use strict';
  const labels={published:'Publicado',in_progress:'En preparación',pending:'Pendiente',unknown:'Sin confirmación',untracked:'Fuera del catálogo'};
  const $=id=>document.getElementById(id), root=new URL('../../',location.href);
  const el=(tag,text,className)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(className)n.className=className;return n;};
  const normal=s=>s.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
  let data=null,department='',municipality='',filter='all',mapVersion=0;const maps=new Map();
  const current=()=>data.departments.find(d=>d.code===department);
  async function json(url){const r=await fetch(url,{credentials:'omit',headers:{Accept:'application/json'}});if(!r.ok)throw Error('No disponible');return r.json();}
  async function geometry(file){if(!maps.has(file))maps.set(file,json(new URL('assets/cobertura/'+file,root)).catch(e=>{maps.delete(file);throw e;}));return maps.get(file);}
  const option=(value,text)=>{const o=el('option',text);o.value=value;return o;};
  function updateUrl(){const url=new URL(location.href);url.search='';if(department)url.searchParams.set('departamento',department);if(municipality)url.searchParams.set('municipio',municipality);history.replaceState(null,'',url);}
  function setDepartment(code){department=data.departments.some(d=>d.code===code)?code:'';municipality='';$('department').value=department;$('department').dispatchEvent(new Event('floating-select:refresh'));updateUrl();renderDetail();renderMap();}
  function setMunicipality(code){municipality=current()?.municipalities.some(m=>m.code===code)?code:'';$('municipality').value=municipality;$('municipality').dispatchEvent(new Event('floating-select:refresh'));updateUrl();renderPlaces();highlight();renderAudience();}
  function renderDetail(){
    const d=current();$('country-button').hidden=!d;$('map-title').textContent=d?d.name:'Colombia';$('map-kicker').textContent=d?'ACÉRCATE A SUS MUNICIPIOS':'ELIGE POR DÓNDE EMPEZAR';
    $('detail-title').textContent=d?'Estado de las guías':'Guías para explorar';$('detail-description').textContent=d?'Estas son las guías del departamento que forman parte de nuestro catálogo.':'Selecciona un departamento o busca entre los destinos del catálogo nacional.';
    $('department-summary').hidden=!d;$('municipality').disabled=!d;$('municipality').replaceChildren(option('','Todos los municipios'));
    if(d){$('department-percent').textContent='En definición';
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
        const choose=()=>d?setMunicipality(code):setDepartment(code);path.addEventListener('click',choose);path.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();choose();}});path.addEventListener('pointerenter',()=>{$('map-hint').textContent=name+' · '+labels[state];});path.addEventListener('focus',()=>{$('map-hint').textContent=name+' · '+labels[state];});svg.append(path);
      });
      $('map').replaceChildren(svg);highlight();
    }catch(e){if(version===mapVersion)$('map').replaceChildren(el('p','El mapa no pudo cargarse. Puedes consultar la misma información con las listas de departamentos y municipios.','map-error'));}
    finally{if(version===mapVersion)$('map').setAttribute('aria-busy','false');}
  }
  function renderAudience(){
    const d=current(),m=d?.municipalities.find(x=>x.code===municipality),days=$('audience-period').value;
    $('audience-scope').textContent=(m?'Interés por '+m.name:d?'Interés por '+d.name:'Audiencia del portal en Colombia')+' · últimos '+days+' días. Los viajeros registrados representan la comunidad general del portal.';
    // No collector/provider is configured. Never fill these slots from coverage fixtures or account stubs.
    $('audience-status').textContent='Medición aún no habilitada. No hay cifras de visitas, registros ni contactos comprobadas para esta selección.';
  }
  async function init(){
    try{
      data=await json(new URL('api/cobertura.php',root));if(!Array.isArray(data.departments)||!data.country)throw Error('Respuesta inválida');
      $('country-published').textContent=data.publication_source_available?data.country.counts.published:'—';$('country-total').textContent=data.publication_source_available?'Guías públicas de Colombia consultadas en el portal':'Publicación temporalmente sin confirmar';$('catalog-scope').textContent='El total nacional previsto está en definición. El listado inicial de trabajo no es el total de destinos turísticos de Colombia; por eso no mostramos un porcentaje de cobertura nacional.';$('country-progress').textContent=data.country.counts.in_progress;
      if(!data.publication_source_available){$('notice').hidden=false;$('notice').textContent='No podemos confirmar ahora cuántas guías están publicadas. Mostramos sin dato, no un cero.';}
      $('updated').textContent='Estado consultado: '+new Intl.DateTimeFormat('es-CO',{dateStyle:'medium',timeStyle:'short',timeZone:'America/Bogota'}).format(new Date(data.checked_at))+' (Colombia). Catálogo: '+data.universe_version+'.';
      data.departments.slice().sort((a,b)=>a.name.localeCompare(b.name,'es')).forEach(d=>$('department').append(option(d.code,d.name)));
      $('department').addEventListener('change',e=>setDepartment(e.target.value));$('municipality').addEventListener('change',e=>setMunicipality(e.target.value));$('country-button').addEventListener('click',()=>setDepartment(''));$('search').addEventListener('input',renderPlaces);$('audience-period').addEventListener('change',renderAudience);
      document.querySelectorAll('[data-filter]').forEach(b=>b.addEventListener('click',()=>{filter=b.dataset.filter;document.querySelectorAll('[data-filter]').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));renderPlaces();}));
      const params=new URL(location.href).searchParams;const start=params.get('departamento')||'',mun=params.get('municipio')||'';setDepartment(start);if(mun)setMunicipality(mun);
    }catch(e){$('notice').hidden=false;$('notice').textContent='No pudimos cargar la cobertura. Vuelve a intentar más tarde; puedes seguir explorando las guías desde Destinos.';$('map').replaceChildren(el('p','Información temporalmente no disponible.','map-error'));$('map').setAttribute('aria-busy','false');$('updated').textContent='Sin actualización disponible.';}
  }
  init();
})();
