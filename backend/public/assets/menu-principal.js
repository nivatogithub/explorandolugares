/* One controller for the server-rendered menu. No editorial or resource injection. */
(()=>{
 'use strict';
 const header=document.querySelector('[data-portal-shell]');
 const button=header?.querySelector('.menu-toggle');
 const nav=header?.querySelector('#menu-principal');
 if(!button||!nav||button.dataset.menuReady)return;
 button.dataset.menuReady='1';
 const close=(restoreFocus=false)=>{
  const wasOpen=header.classList.contains('menu-open');
  header.classList.remove('menu-open');button.setAttribute('aria-expanded','false');
  if(wasOpen&&restoreFocus)button.focus();
 };
 button.addEventListener('click',()=>{
  const open=button.getAttribute('aria-expanded')!=='true';
  header.classList.toggle('menu-open',open);button.setAttribute('aria-expanded',String(open));
 });
 nav.addEventListener('click',event=>{if(event.target.closest('a'))close();});
 document.addEventListener('keydown',event=>{if(event.key==='Escape')close(true);});
 document.addEventListener('click',event=>{if(!header.contains(event.target))close();});
 matchMedia('(min-width:1191px)').addEventListener('change',()=>close());
})();

// Prevent the native ghost-image drag without blocking links, taps or scrolling.
document.addEventListener('dragstart',event=>{if(event.target.closest?.('img,picture'))event.preventDefault();});

// Keep the reading position when opening the equivalent page in another language.
(()=>{
 const key='el-language-scroll';
 document.addEventListener('click',event=>{
  const link=event.target.closest?.('.header-language-options a[data-language-target="equivalent-page"]');
  if(!link||event.defaultPrevented||event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
  const target=new URL(link.href,location.href);if(target.origin!==location.origin)return;
  try{sessionStorage.setItem(key,JSON.stringify({path:target.pathname,x:window.scrollX,y:window.scrollY,time:Date.now()}));}catch{}
 });
 let saved;try{saved=JSON.parse(sessionStorage.getItem(key)||'null');sessionStorage.removeItem(key);}catch{}
 if(!saved||saved.path!==location.pathname||Date.now()-saved.time>30000)return;
 const restore=()=>{
  const root=document.documentElement,behavior=root.style.scrollBehavior;root.style.scrollBehavior='auto';
  window.scrollTo({left:saved.x,top:saved.y,behavior:'instant'});root.style.scrollBehavior=behavior;
 };
 if(document.readyState==='complete')requestAnimationFrame(restore);
 else window.addEventListener('load',()=>requestAnimationFrame(()=>requestAnimationFrame(restore)),{once:true});
})();
