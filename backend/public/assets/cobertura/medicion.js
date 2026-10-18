(() => {
 'use strict';
 if(window.elMeasurementLoaded)return;window.elMeasurementLoaded=true;
 const endpoint='/api/medicion.php',key='el_measurement_choice_v1',version='measurement-v1';
 const excluded=/\/(admin|api|business|negocios\/(portal|registro|acceso|salir|cambiar-clave))(\/|$)/.test(location.pathname);
 if(excluded||document.querySelector('.destination-preview-banner'))return;
 const lang=document.documentElement.lang.startsWith('en')?'en':document.documentElement.lang.startsWith('pt')?'pt':'es';
 const copy={es:['¿Aceptas la medición opcional?','El conteo básico, cuando está habilitado, no usa cookies. Con tu permiso usamos cookies propias para estimar visitantes, sesiones e interés por negocios. No guardamos tu IP, correos ni búsquedas en eventos. Puedes rechazar esta medición opcional.','Aceptar medición','Rechazar','Preferencias de privacidad','Privacidad'],en:['Allow optional audience measurement?','Basic page counts, when enabled, use no cookies. With your permission we use first-party cookies to estimate visitors, sessions and interest in businesses. Events do not store your IP, email or searches. You can decline optional measurement.','Allow measurement','Decline','Privacy preferences','Privacy'],pt:['Aceita a medição opcional?','A contagem básica, quando habilitada, não usa cookies. Com sua permissão usamos cookies próprias para estimar visitantes, sessões e interesse em negócios. Os eventos não guardam seu IP, e-mail ou buscas. Você pode recusar a medição opcional.','Aceitar medição','Recusar','Preferências de privacidade','Privacidade']}[lang];
 // External stylesheet also works on pages with style-src 'self'.
 const style=document.createElement('link');style.rel='stylesheet';style.href='/assets/cobertura/medicion.css?v=b233a1ab9b8f';document.head.append(style);
 const storage={get(){try{return JSON.parse(localStorage.getItem(key)||'null');}catch{return null;}},set(v){try{localStorage.setItem(key,JSON.stringify(v));}catch{}}};
 let enabled=false,allowed=false,banner=null,queue=Promise.resolve();
 const signal=navigator.globalPrivacyControl===true||navigator.doNotTrack==='1';
 function source(){try{if(new URL(location.href).searchParams.has('utm_source'))return 'campaign';if(!document.referrer)return 'direct_unknown';const host=new URL(document.referrer).hostname;if(host===location.hostname)return 'direct_unknown';if(/(^|\.)instagram\.com$/.test(host))return 'instagram';if(/(^|\.)facebook\.com$/.test(host))return 'facebook';if(/(^|\.)(google\.[a-z.]+|bing\.com|duckduckgo\.com|yahoo\.com)$/.test(host))return 'search';return 'referral';}catch{return 'direct_unknown';}}
 const acquisition=source();
 function send(event,extra={}){if(!allowed||!enabled||signal)return Promise.resolve();const payload={event,event_id:crypto.randomUUID(),path:location.pathname,consent:true,policy_version:version,source:acquisition,...extra};queue=queue.then(async()=>{if(!allowed)return;try{await fetch(endpoint,{method:'POST',credentials:'same-origin',keepalive:true,headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});}catch{}});return queue;}

 // Count once per document, independently from optional consent. No cookies or IDs.
 function basicPage(){if(signal)return Promise.resolve();return fetch('/api/actividad.php',{method:'POST',credentials:'omit',referrerPolicy:'no-referrer',keepalive:true,headers:{'Content-Type':'application/json'},body:JSON.stringify({path:location.pathname})}).then(r=>r.ok?r.json():null).then(result=>{if(result?.accepted)window.dispatchEvent(new Event('el:basic-counted'));return result;}).catch(()=>null);}
 window.elBasicPageCount=basicPage();
 // Visible-card impressions: >=50% for one continuous second, once per document.
 function trackCards(){
  const cards=[...document.querySelectorAll('.directory-card[data-business-id]')];
  if(!cards.length||!('IntersectionObserver' in window)||signal)return;
  const seen=new WeakSet(),clicked=new WeakSet(),visible=new Map(),timers=new Map();
  const count=(card,metric)=>fetch('/api/actividad.php',{method:'POST',credentials:'omit',referrerPolicy:'no-referrer',keepalive:true,headers:{'Content-Type':'application/json'},body:JSON.stringify({path:location.pathname,metric,business_id:card.dataset.businessId})}).catch(()=>{});
  const cancel=card=>{clearTimeout(timers.get(card));timers.delete(card);};
  const start=card=>{if(seen.has(card)||timers.has(card)||document.visibilityState!=='visible')return;timers.set(card,setTimeout(()=>{timers.delete(card);if(visible.get(card)&&card.getClientRects().length>0&&document.visibilityState==='visible'&&!seen.has(card)){seen.add(card);count(card,'card_impression');observer.unobserve(card);}},1000));};
  const observer=new IntersectionObserver(entries=>{for(const entry of entries){const show=entry.isIntersecting&&entry.intersectionRatio>=.5;visible.set(entry.target,show);if(show)start(entry.target);else cancel(entry.target);}},{threshold:[0,.5]});
  cards.forEach(card=>observer.observe(card));
  document.addEventListener('visibilitychange',()=>{for(const card of cards){if(document.visibilityState!=='visible')cancel(card);else if(visible.get(card))start(card);}});
  document.addEventListener('click',event=>{if(!event.isTrusted)return;const link=event.target.closest('a[href]'),card=link?.closest('.directory-card[data-business-id]');if(!card||clicked.has(card))return;try{const url=new URL(link.href,location.href),id=/^\/(?:en\/|pt-br\/)?negocios\/([a-f0-9-]{16,64})\/?$/i.exec(url.pathname);if(url.origin===location.origin&&id?.[1]===card.dataset.businessId){clicked.add(card);count(card,'profile_click');}}catch{}});
 }
 window.elBasicPageCount.then(result=>{if(result?.accepted)trackCards();});
 window.elMeasure=send;
 function page(){send('page_view');if(/\/negocios\/[a-f0-9-]{16,64}\/?$/i.test(location.pathname))send('business_view');}
 function choose(value){allowed=value&&!signal;storage.set({allowed,version,expires:Date.now()+90*86400000});banner?.remove();banner=null;if(allowed)page();else fetch(endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'revoke'})}).catch(()=>{});}
 function show(){banner?.remove();banner=document.createElement('aside');banner.setAttribute('aria-label',copy[4]);banner.className='el-measurement-consent';banner.dataset.privacyHeading={es:'Tu privacidad, tu elección',en:'Your privacy, your choice',pt:'Sua privacidade, sua escolha'}[lang];

 const title=document.createElement('h2');title.textContent=copy[0];const text=document.createElement('p');text.textContent=copy[1];banner.append(title,text);
 const actions=document.createElement('div');actions.className='el-measurement-actions';banner.append(actions);
 for(const [label,value] of [[copy[2],true],[copy[3],false]]){const b=document.createElement('button');b.type='button';b.textContent=label;b.disabled=value&&signal;b.addEventListener('click',()=>choose(value));actions.append(b);}
 const privacy=document.createElement('a');privacy.href=(lang==='en'?'/en':lang==='pt'?'/pt-br':'')+'/privacidad/';privacy.textContent=copy[5];banner.append(privacy);document.body.append(banner);}
 document.addEventListener('click',e=>{const a=e.target.closest('a[href]');if(!a)return;const profile=/\/negocios\/([a-f0-9-]{16,64})\/?$/i.exec(location.pathname);const container=a.closest('[data-business-id],article[id^="negocio-"]');const id=container?.dataset.businessId||container?.id?.replace(/^negocio-/, '')||profile?.[1];if(!id||(!container&&!a.closest('.business-profile-contact')))return;const href=a.getAttribute('href');const channel=href.startsWith('tel:')?'phone':href.startsWith('mailto:')?'email':/wa\.me|api\.whatsapp\.com/.test(href)?'whatsapp':/^https?:/.test(href)?'website':null;if(channel)send('contact_click',{business_id:id,channel});});
 fetch(endpoint,{credentials:'same-origin'}).then(r=>r.ok?r.json():null).then(status=>{if(!status?.enabled)return;enabled=true;const prefs=document.createElement('button');prefs.type='button';prefs.className='el-measurement-preferences';prefs.textContent=copy[4];const icon=document.createElementNS('http://www.w3.org/2000/svg','svg');icon.setAttribute('viewBox','0 0 24 24');icon.setAttribute('aria-hidden','true');icon.innerHTML='<path d="M12 3 4.5 6v5.5c0 4.6 3.1 7.7 7.5 9.5 4.4-1.8 7.5-4.9 7.5-9.5V6L12 3Z"/><path d="m8.5 12 2.3 2.3 4.7-4.7"/>';prefs.prepend(icon);prefs.addEventListener('click',show);(document.querySelector('footer')||document.body).append(prefs);const saved=storage.get();if(signal){choose(false);return;}if(saved?.version===version&&saved.expires>Date.now()){allowed=saved.allowed===true;if(allowed)page();}else show();}).catch(()=>{});
})();
