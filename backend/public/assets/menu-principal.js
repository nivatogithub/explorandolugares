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
