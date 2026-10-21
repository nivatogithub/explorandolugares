/* Reusable, same-page links between a place and its related experience. */
(() => {
  'use strict';
  const relations = [{destination:'zipaquira',place:'estacion-tren',experience:'tren-sabana',icon:'rail'}];
  const language=document.documentElement.lang.toLowerCase();
  const labels=language.startsWith('en')
    ? {toExperience:'See the train experience',toPlace:'See the arrival station',group:'Related visit',rail:'Railway'}
    : language.startsWith('pt')
      ? {toExperience:'Ver a experiência de trem',toPlace:'Ver a estação de chegada',group:'Visita relacionada',rail:'Ferrovia'}
      : {toExperience:'Ver la experiencia del tren',toPlace:'Ver la estación de llegada',group:'Visita relacionada',rail:'Ferrocarril'};
  function icon() {
    const ns='http://www.w3.org/2000/svg',svg=document.createElementNS(ns,'svg');
    for(const [key,value] of Object.entries({viewBox:'0 0 24 24',width:'18',height:'18',fill:'none',stroke:'currentColor','stroke-width':'1.7','aria-hidden':'true',focusable:'false'}))svg.setAttribute(key,value);
    for(const [tag,attributes] of [['rect',{x:'6',y:'3',width:'12',height:'14',rx:'3'}],['path',{d:'M6 10h12M9 17l-3 4m9-4 3 4M8 20h8M9 13h.01M15 13h.01'}]]){
      const element=document.createElementNS(ns,tag);for(const [key,value]of Object.entries(attributes))element.setAttribute(key,value);svg.append(element);
    }
    svg.style.cssText='display:inline-block;vertical-align:-.15em;flex:none';return svg;
  }
  function reveal(target) {
    const grid=target.closest('.highlight-grid,.destination-experience-list');
    if(!grid)return;
    const button=grid.nextElementSibling;
    if(!button?.matches('.row-load-more'))return;
    // Use the existing responsive pager, preserving its row count and resize state.
    for(let count=0;target.hidden&&!button.hidden&&count<grid.children.length;count++)button.click();
  }
  function connect(source,target,text,key) {
    if(source.querySelector(`[data-place-relation="${key}"]`))return;
    const link=document.createElement('a');link.className='text-link place-relation-link';
    link.dataset.placeRelation=key;link.href='#'+target.id;
    link.style.cssText='display:inline-flex;align-items:center;gap:.35rem;font-size:.85rem;margin-top:.35rem';
    link.append(icon(),document.createTextNode(text));
    link.addEventListener('click',()=>{reveal(target);target.focus({preventScroll:true});});
    const copy=source.querySelector('.destination-experience-copy')||source;copy.append(link);
  }
  function install() {
    for(const relation of relations){
      if(!location.pathname.split('/').includes(relation.destination))continue;
      const place=document.querySelector(`[data-place-slug="${relation.place}"]`);
      const experience=document.querySelector(`[data-experience-slug="${relation.experience}"]`);
      if(!place||!experience)continue;
      if(!place.id)place.id='lugar-'+relation.place;
      if(!experience.id)experience.id='experiencia-'+relation.experience;
      place.tabIndex=-1;experience.tabIndex=-1;
      const heading=place.querySelector('h3,h4');
      if(heading&&!heading.querySelector('[data-rail-icon]')){
        const symbol=icon();symbol.dataset.railIcon='1';symbol.style.marginRight='.3rem';heading.prepend(symbol);
      }
      connect(place,experience,labels.toExperience,'place-to-experience');
      connect(experience,place,labels.toPlace,'experience-to-place');
    }
  }
  function revealHash(){
    let id;try{id=decodeURIComponent(location.hash.slice(1));}catch{return;}
    if(id){const target=document.getElementById(id);if(target?.matches('[data-place-slug],[data-experience-slug]')){reveal(target);target.scrollIntoView({block:'start'});}}
  }
  install();document.addEventListener('DOMContentLoaded',install);
  for(const event of ['destination:cards-updated'])document.addEventListener(event,install);
  window.addEventListener('load',()=>{install();requestAnimationFrame(revealHash);});
  window.addEventListener('hashchange',revealHash);
})();
