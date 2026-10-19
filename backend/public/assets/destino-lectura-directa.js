/* Compact city layout: open content, progressive rows, history without side content. */
(()=>{
 const cities=['bogota','medellin','santa-marta','zipaquira','santa-fe-de-antioquia','jardin','covenas'];if(!cities.some(s=>location.pathname.split('/').includes(s)))return;
 const l=document.documentElement.lang.toLowerCase(),en=l.startsWith('en'),pt=l.startsWith('pt');
 const titles=en?['A brief history','Places to visit','Experiences','Directory']:pt?['História em breve','O que conhecer','Experiências','Diretório']:['Historia en breve','Qué conocer','Experiencias','Directorio'];
 let queued=false;
 function normalize(){
  queued=false;document.body.classList.add('destination-direct-reading');
  for(const detail of [...document.querySelectorAll('main details')].reverse()){
   const summary=detail.querySelector(':scope > summary');
   if(detail.classList.contains('place-disclosure')){
    if(summary)detail.before(...summary.childNodes);
    const content=detail.querySelector(':scope > .place-disclosure-content');if(content)detail.before(...content.childNodes);
   }else{
    if(summary){const h=document.createElement('h3');h.textContent=summary.textContent;detail.before(h);summary.remove();}
    detail.before(...detail.childNodes);
   }
   detail.remove();
  }
  const story=document.querySelector('#descubre');if(story){
   story.querySelectorAll('figure,aside,.eyebrow,.credit,img').forEach(n=>n.remove());
   const h=story.querySelector('h2');if(h&&h.textContent!==titles[0])h.textContent=titles[0];
  }
  for(const [selector,title] of [['#que-conocer',titles[1]],['.destination-experiences',titles[2]],['#lugares',titles[3]]]){
   const area=document.querySelector(selector);if(!area)continue;
   const head=area.querySelector(':scope > .sectionhead,:scope > .destination-experiences-heading');if(!head)continue;
   head.querySelectorAll('p,.eyebrow').forEach(n=>n.remove());
   const old=head.querySelector('h2,h3');if(old&&(old.tagName!=='H2'||old.textContent!==title)){const h=document.createElement('h2');h.id=old.id;h.textContent=title;old.replaceWith(h);}
  }
  document.querySelectorAll('.destination-experience-copy > .story-number,#lugares .directory-description').forEach(n=>n.remove());
 }
 function schedule(){if(queued)return;queued=true;queueMicrotask(normalize);}
 normalize();window.addEventListener('load',schedule,{once:true});
 for(const name of ['destination:cards-updated','santa-marta-review-ready','medellin-review-ready'])document.addEventListener(name,schedule);
 new MutationObserver(records=>{if(records.some(r=>[...r.addedNodes].some(n=>n.nodeType===1&&(n.matches('details,.sectionhead,.destination-experiences-heading,.eyebrow')||n.querySelector('details')))))schedule();}).observe(document.querySelector('main')||document.body,{childList:true,subtree:true});
})();
