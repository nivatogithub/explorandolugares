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
  if(directory){createDirectoryPager(grid,cards,index);return;}
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
 function createDirectoryPager(grid,cards,index){
  const words=lang.startsWith('en')?['Previous','Next','Directory pages','Page']:lang.startsWith('pt')?['Anterior','Próxima','Páginas do diretório','Página']:['Anterior','Siguiente','Páginas del directorio','Página'];
  const id=grid.id||'directory-grid-'+index;grid.id=id;
  const key='directory-page:'+location.pathname+':'+index;
  let page=1;try{page=Math.max(1,Number(sessionStorage.getItem(key))||1);}catch{}
  const pageSize=()=>{const columns=getComputedStyle(grid).gridTemplateColumns;return 8*(columns&&columns!=='none'?Math.max(1,columns.trim().split(/\s+/).length):1);};
  const nav=document.createElement('nav');nav.className='directory-pagination';nav.setAttribute('aria-label',words[2]);grid.after(nav);
  function update(reset=false){
   if(reset)page=1;
   const category=document.querySelector('.directory-tabs [aria-selected="true"]')?.dataset.category||'';
   const eligible=cards.filter(c=>!category||(c.dataset.categories||c.dataset.category||'').split(/\s+/).includes(category));
   const size=pageSize();
   const pages=Math.max(1,Math.ceil(eligible.length/size));page=Math.min(page,pages);
   const visible=new Set(eligible.slice((page-1)*size,page*size));cards.forEach(c=>c.hidden=!visible.has(c));
   nav.replaceChildren();nav.hidden=pages===1;
   function control(text,target,disabled,current=false){
    const button=document.createElement('button');button.type='button';button.textContent=text;button.disabled=disabled;button.setAttribute('aria-controls',id);
    if(current)button.setAttribute('aria-current','page');
    button.addEventListener('click',()=>{page=target;update();grid.scrollIntoView({block:'start',behavior:'smooth'});nav.querySelector('[aria-current="page"]')?.focus({preventScroll:true});});nav.append(button);
   }
   control(words[0],page-1,page===1);
   for(let n=1;n<=pages;n++){
    if(n===1||n===pages||Math.abs(n-page)<=1)control(String(n),n,false,n===page);
    else if(n===page-2||n===page+2){const dots=document.createElement('span');dots.textContent='…';nav.append(dots);}
   }
   control(words[1],page+1,page===pages);
   try{sessionStorage.setItem(key,String(page));}catch{}
  }
  controllers.push({update,directory:true});update();
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
