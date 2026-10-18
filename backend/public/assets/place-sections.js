/* Shared place navigation: native, keyboard-accessible collapsible sections. */
(()=>{
 'use strict';
 const highlights=document.getElementById('que-conocer');
 const experiences=document.querySelector('.destination-experiences');
 // Experiences are an independent section, after attractions, in every place.
 if(highlights&&experiences&&highlights.contains(experiences))highlights.after(experiences);
 // semirrealista-open-sections: private city proposals follow the approved open layout.
 if(new URLSearchParams(location.search).get('preview')==='1'&&['bogota','medellin','santa-marta'].some(s=>location.pathname.split('/').includes(s)))return;
 const targets=[highlights,experiences,document.getElementById('lugares'),...document.querySelectorAll('[data-directory-tier]')].filter(Boolean);
 for(const section of targets){
  if(section.querySelector(':scope > details.place-disclosure'))continue;
  const heading=section.querySelector(':scope > .sectionhead,:scope > .destination-experiences-heading,:scope > .directory-tier-heading');
  if(!heading)continue;
  const details=document.createElement('details');details.className='place-disclosure';
  const summary=document.createElement('summary');summary.className='place-disclosure-summary';
  summary.append(heading);details.append(summary);
  const content=document.createElement('div');content.className='place-disclosure-content';
  while(section.firstChild)content.append(section.firstChild);
  details.append(content);section.append(details);
 }
 function reveal(){
  let id;try{id=decodeURIComponent(location.hash.slice(1));}catch{return;}
  if(!id)return;
  const target=document.getElementById(id);if(!target)return;
  for(let node=target;node;node=node.parentElement){
   if(node.matches('details.place-disclosure'))node.open=true;
   if(node.matches('section')){const child=node.querySelector(':scope > details.place-disclosure');if(child)child.open=true;}
  }
  requestAnimationFrame(()=>target.scrollIntoView({block:'start'}));
 }
 window.addEventListener('hashchange',reveal);reveal();
})();
