(() => {
 'use strict';
 if(window.elMeasurementLoaded)return;window.elMeasurementLoaded=true;
 const exclusionKey='el_measurement_excluded_v1';let browserExcluded=false;
 try{browserExcluded=localStorage.getItem(exclusionKey)==='1';localStorage.removeItem?.('el_measurement_choice_v1');}catch{}
 const signal=navigator.globalPrivacyControl===true||navigator.doNotTrack==='1';
 const trackingBlocked=()=>signal||browserExcluded;
 const excluded=/\/(admin|api|business|negocios\/(portal|registro|acceso|salir|cambiar-clave))(\/|$)/.test(location.pathname);
 if(excluded||document.querySelector('.destination-preview-banner'))return;
 // Retire legacy analytic cookies even on excluded pages. No new IDs are issued.
 fetch('/api/medicion.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'revoke'})}).catch(()=>{});
 const lang=document.documentElement.lang.startsWith('en')?'en':document.documentElement.lang.startsWith('pt')?'pt':'es';
 const text={es:['Privacidad y estadísticas','Contamos consultas y clics de forma agregada, sin cookies analíticas ni identificar navegadores. El país de conexión se obtiene temporalmente de la IP; no guardamos la IP en estas estadísticas.','Excluir este navegador de las estadísticas','Cerrar','Privacidad'],en:['Privacy and statistics','We count page views and clicks in aggregate, without analytics cookies or browser identifiers. Connection country is derived temporarily from IP; the IP is not stored in these statistics.','Exclude this browser from statistics','Close','Privacy'],pt:['Privacidade e estatísticas','Contamos consultas e cliques de forma agregada, sem cookies analíticos nem identificadores de navegador. O país da conexão é obtido temporariamente do IP; não armazenamos o IP nestas estatísticas.','Excluir este navegador das estatísticas','Fechar','Privacidade']}[lang];
 if(!document.querySelector('link[href*="/assets/cobertura/medicion.css?v=2825cd17105b"]')){const style=document.createElement('link');style.rel='stylesheet';style.href='/assets/cobertura/medicion.css?v=2825cd17105b';document.head.append(style);}
 function count(payload){if(trackingBlocked())return Promise.resolve(null);return fetch('/api/actividad.php',{method:'POST',credentials:'omit',referrerPolicy:'no-referrer',keepalive:true,headers:{'Content-Type':'application/json'},body:JSON.stringify({path:location.pathname,...payload})}).then(r=>r.ok?r.json():null).catch(()=>null);}
 window.elBasicPageCount=count({});
 function trackCards(){
  const cards=[...document.querySelectorAll('.directory-card[data-business-id]')];
  if(!cards.length||!('IntersectionObserver' in window)||trackingBlocked())return;
  const seen=new WeakSet(),clicked=new WeakSet(),visible=new Map(),timers=new Map();
  const count=(card,metric)=>trackingBlocked()?Promise.resolve():fetch('/api/actividad.php',{method:'POST',credentials:'omit',referrerPolicy:'no-referrer',keepalive:true,headers:{'Content-Type':'application/json'},body:JSON.stringify({path:location.pathname,metric,business_id:card.dataset.businessId})}).catch(()=>{});
  const cancel=card=>{clearTimeout(timers.get(card));timers.delete(card);};
  const start=card=>{if(seen.has(card)||timers.has(card)||document.visibilityState!=='visible')return;timers.set(card,setTimeout(()=>{timers.delete(card);if(visible.get(card)&&card.getClientRects().length>0&&document.visibilityState==='visible'&&!seen.has(card)){seen.add(card);count(card,'card_impression');observer.unobserve(card);}},1000));};
  const observer=new IntersectionObserver(entries=>{for(const entry of entries){const show=entry.isIntersecting&&entry.intersectionRatio>=.5;visible.set(entry.target,show);if(show)start(entry.target);else cancel(entry.target);}},{threshold:[0,.5]});
  cards.forEach(card=>observer.observe(card));
  document.addEventListener('visibilitychange',()=>{for(const card of cards){if(document.visibilityState!=='visible')cancel(card);else if(visible.get(card))start(card);}});
  document.addEventListener('click',event=>{if(!event.isTrusted)return;const link=event.target.closest('a[href]'),card=link?.closest('.directory-card[data-business-id]');if(!card||clicked.has(card))return;try{const url=new URL(link.href,location.href),id=/^\/(?:en\/|pt-br\/)?negocios\/([a-f0-9-]{16,64})\/?$/i.exec(url.pathname);if(url.origin===location.origin&&id?.[1]===card.dataset.businessId){clicked.add(card);count(card,'profile_click');}}catch{}});
 }
 window.elBasicPageCount.then(result=>{if(result?.accepted)trackCards();});
 window.elMeasure=()=>Promise.resolve(); // Legacy UI hooks never create sessions or IDs.
 document.addEventListener('click',event=>{
  if(!event.isTrusted)return;const link=event.target.closest('a[href]');if(!link)return;
  const profile=/\/negocios\/([a-f0-9-]{16,64})\/?$/i.exec(location.pathname),card=link.closest('[data-business-id],article[id^="negocio-"]');
  const id=card?.dataset.businessId||card?.id?.replace(/^negocio-/,'')||profile?.[1];
  if(!id||(!card&&!link.closest('.business-profile-contact')))return;
  const href=link.getAttribute('href')||'',channel=href.startsWith('tel:')?'phone':href.startsWith('mailto:')?'email':/wa\.me|api\.whatsapp\.com/.test(href)?'whatsapp':/^https?:/.test(href)?'website':null;
  if(channel)count({metric:'contact_click',business_id:id,channel});
 });
 const prefs=document.querySelector('footer .el-measurement-preferences')||document.createElement('button');prefs.type='button';prefs.className='el-measurement-preferences';
 const label=()=>{prefs.textContent=text[0];prefs.setAttribute('aria-label',text[0]+(browserExcluded?' · '+text[2]:''));};label();
 let panel=null;prefs.addEventListener('click',()=>{
  panel?.remove();panel=document.createElement('aside');panel.className='el-measurement-consent';panel.setAttribute('aria-label',text[0]);
  const title=document.createElement('h2');title.textContent=text[0];const description=document.createElement('p');description.textContent=text[1];
  const line=document.createElement('label');line.className='el-measurement-exclusion';const box=document.createElement('input');box.type='checkbox';box.checked=browserExcluded;const caption=document.createElement('span');caption.textContent=text[2];line.append(box,caption);
  box.addEventListener('change',()=>{try{localStorage.setItem(exclusionKey,box.checked?'1':'0');if(localStorage.getItem(exclusionKey)!==(box.checked?'1':'0'))throw Error();browserExcluded=box.checked;label();}catch{box.checked=browserExcluded;caption.textContent=text[2]+' — no se pudo guardar';}});
  const close=document.createElement('button');close.type='button';close.textContent=text[3];close.addEventListener('click',()=>{panel.remove();panel=null;prefs.focus?.();});
  const privacy=document.createElement('a');privacy.href=(lang==='en'?'/en':lang==='pt'?'/pt-br':'')+'/privacidad/';privacy.textContent=text[4];panel.append(title,description,line,close,privacy);document.body.append(panel);
 });if(!document.querySelector('footer .el-measurement-preferences'))(document.querySelector('footer')||document.body).append(prefs);
 window.addEventListener('storage',event=>{if(event.key===exclusionKey){browserExcluded=event.newValue==='1';label();}});
})();
