/* Compact business cards: preserve genuine destinations and contact links. */
(()=>{
 const icons={
 map:'<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
 whatsapp:'<path d="M21 11.5a9 9 0 0 1-13 8L3 21l1.5-5A9 9 0 1 1 21 11.5Z"/><path d="m8 7 2 3-1 1c1 2 2 3 4 4l1-1 3 2c-1 3-4 2-6 0s-5-5-3-9Z"/>',
 instagram:'<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".7"/>',
 email:'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
 page:'<circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18M5 6h14M5 18h14"/>',
 star:'<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9Z"/>'
 };
 icons.info='<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7v.5"/>';
 const svg=kind=>'<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'+icons[kind]+'</svg>';
 const lang=document.documentElement.lang.toLowerCase();const labels=lang.startsWith('en')?{map:'Find on map',whatsapp:'WhatsApp',instagram:'Instagram',email:'Email',page:'Open business page'}:lang.startsWith('pt')?{map:'Ver localização',whatsapp:'WhatsApp',instagram:'Instagram',email:'E-mail',page:'Abrir página do negócio'}:{map:'Ver ubicación',whatsapp:'WhatsApp',instagram:'Instagram',email:'Correo',page:'Ver página del negocio'};
 function improve(){
  for(const card of document.querySelectorAll('.directory-card')){
   if(card.dataset.iconLayout==='1')continue;card.dataset.iconLayout='1';
   const premium=card.classList.contains('directory-card-tier-page_premium');
   card.querySelectorAll('.directory-featured-badge').forEach(b=>{if(!premium)b.remove();else{b.innerHTML=svg('star');b.title='Premium';b.setAttribute('aria-label','Premium');b.setAttribute('role','img');b.className='directory-premium-star';}});
   const body=card.querySelector('.directory-card-body');if(!body)continue;
   const title=body.querySelector('h3');const category=body.querySelector(':scope>.eyebrow');const logo=body.querySelector('.directory-business-mark');
   const heading=document.createElement('div');heading.className='directory-compact-heading';const copy=document.createElement('div');copy.className='directory-heading-copy';
   if(logo)heading.append(logo);if(title)copy.append(title);if(category){category.classList.add('directory-category-chip');copy.append(category);}heading.append(copy);body.prepend(heading);
   if(category){const tab=document.querySelector('.directory-tabs [data-category="'+(card.dataset.category||'')+'"]');if(tab){const chip=document.createElement('button');chip.type='button';chip.className=category.className;chip.textContent=category.textContent;chip.title=lang.startsWith('en')?'Filter by this category':lang.startsWith('pt')?'Filtrar por esta categoria':'Filtrar por esta categoría';chip.addEventListener('click',()=>tab.click());category.replaceWith(chip);}}
   const full=title?.getAttribute('title');const name=title?.querySelector('a,span');if(full&&name)name.textContent=full;
   const actions=document.createElement('nav');actions.className='directory-icon-actions';actions.setAttribute('aria-label',lang.startsWith('en')?'Business actions':lang.startsWith('pt')?'Ações do negócio':'Acciones del negocio');
   for(const [selector,kind] of [['.directory-location-link','map'],['.directory-contact-whatsapp','whatsapp'],['.directory-contact-instagram','instagram'],['.directory-contact-email','email'],['.directory-business-page-link','page']]){
    const a=body.querySelector(selector);if(!a)continue;a.classList.add('directory-icon-action');a.title=labels[kind];a.setAttribute('aria-label',labels[kind]);a.innerHTML=svg(kind);actions.append(a);
   }
   const status=body.querySelector(':scope>p small');if(status){const note=status.parentElement;note.hidden=true;note.classList.add('directory-status-note');const info=document.createElement('button');info.type='button';info.className='directory-icon-action';info.title=status.textContent;info.setAttribute('aria-label',status.textContent);info.setAttribute('aria-expanded','false');info.innerHTML=svg('info');info.addEventListener('click',()=>{note.hidden=!note.hidden;info.setAttribute('aria-expanded',String(!note.hidden));});actions.prepend(info);}
   if(actions.childElementCount)body.append(actions);else actions.remove();
   body.querySelectorAll('.directory-contact-links').forEach(n=>{if(!n.querySelector('a'))n.remove();});
  }
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',improve,{once:true});else improve();
 window.addEventListener('load',improve,{once:true});
})();
