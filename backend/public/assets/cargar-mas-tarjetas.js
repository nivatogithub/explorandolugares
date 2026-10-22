/* Open sections, progressively reveal complete responsive rows. */
(()=>{
 const lang=document.documentElement.lang.toLowerCase();
 const label=lang.startsWith('en')?'Load more':lang.startsWith('pt')?'Carregar mais':'Cargar más';
 const controllers=[];
 function create(grid,index){
  if(grid.dataset.rowPager)return;grid.dataset.rowPager='1';
  if(grid.id==='atractivos-destino')document.getElementById('cargar-mas-atractivos')?.remove();
  let cards=[...grid.children].filter(n=>n.matches('article,.directory-card,.destination-experience-card'));
  if(!cards.length)return;
  const directory=grid.classList.contains('directory-grid');
  const photo=!directory||cards.some(c=>[...c.querySelectorAll('img')].some(i=>!i.closest('.directory-business-mark')));
  const step=photo?2:4;let rows=step;
  const id=grid.id||'cards-grid-'+index;grid.id=id;
  if(grid.id==='atractivos-destino')document.getElementById('cargar-mas-atractivos')?.remove();
  const button=document.createElement('button');button.type='button';button.className='destination-load-more row-load-more';button.textContent=label;button.setAttribute('aria-controls',id);grid.after(button);
  const columns=()=>{const value=getComputedStyle(grid).gridTemplateColumns;return value&&value!=='none'?Math.max(1,value.trim().split(/\s+/).length):1;};
  function update(reset=false){
   cards=[...grid.children].filter(n=>n.matches('article,.directory-card,.destination-experience-card'));
   if(reset)rows=step;
   const selected=directory?document.querySelector('.directory-tabs [aria-selected="true"]'):null;
   const category=selected?.dataset.category||'';
   const eligible=cards.filter(c=>!category||(c.dataset.categories||c.dataset.category||'').split(/\s+/).includes(category));
   const visible=new Set(eligible.slice(0,rows*columns()));
   cards.forEach(c=>c.hidden=!visible.has(c));
   button.hidden=visible.size>=eligible.length;
   button.setAttribute('aria-label',label+' ('+Math.max(0,eligible.length-visible.size)+')');
  }
  button.addEventListener('click',()=>{rows+=step;update();});
  controllers.push({update,directory});update();
 }
 function init(){document.querySelectorAll('.highlight-grid,.destination-experience-list,.directory-grid').forEach(create);}
 for(const event of ['santa-marta-review-ready','destination:cards-updated','medellin-review-ready'])document.addEventListener(event,()=>{init();controllers.forEach(c=>c.update());});
 // Runs after the compact-layout and directory grouping handlers.
 window.addEventListener('load',()=>{init();queueMicrotask(()=>controllers.forEach(c=>c.update()));},{once:true});
 if(document.readyState==='complete')init();
 document.addEventListener('click',e=>{if(e.target.closest('.directory-tabs [role="tab"]'))queueMicrotask(()=>controllers.filter(c=>c.directory).forEach(c=>c.update(true)));});
 document.addEventListener('keydown',e=>{if(e.target.closest('.directory-tabs [role="tab"]')&&['ArrowLeft','ArrowRight','Home','End'].includes(e.key))queueMicrotask(()=>controllers.filter(c=>c.directory).forEach(c=>c.update(true)));});
 document.addEventListener('toggle',e=>{if(e.target.matches('details'))queueMicrotask(()=>controllers.forEach(c=>c.update()));},true);
 let resize;window.addEventListener('resize',()=>{clearTimeout(resize);resize=setTimeout(()=>controllers.forEach(c=>c.update()),100);});
})();
