(()=>{
 'use strict';
 const menu=document.querySelector('[data-admin-navigation]');if(!menu)return;
 const sidebar=menu.closest('.admin-sidebar'),small=matchMedia('(max-width:760px)'),key='el-admin-navigation-position';
 const apply=()=>{menu.open=!small.matches;};apply();small.addEventListener('change',apply);
 sidebar.addEventListener('click',event=>{
  const link=event.target.closest('nav a');
  if(!link||event.defaultPrevented||event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey||link.target==='_blank')return;
  const target=new URL(link.href,location.href);if(target.origin!==location.origin||!target.pathname.startsWith('/admin/'))return;
  try{sessionStorage.setItem(key,JSON.stringify({path:target.pathname,search:target.search,top:sidebar.scrollTop,y:window.scrollY,open:menu.open,time:Date.now(),anchor:!!target.hash}));}catch{}
 });
 let saved;try{saved=JSON.parse(sessionStorage.getItem(key)||'null');sessionStorage.removeItem(key);}catch{}
 if(!saved||saved.path!==location.pathname||saved.search!==location.search||Date.now()-saved.time>30000)return;
 menu.open=saved.open;
 const restore=()=>{sidebar.scrollTop=saved.top;if(!saved.anchor){const root=document.documentElement,old=root.style.scrollBehavior;root.style.scrollBehavior='auto';window.scrollTo({top:saved.y,behavior:'instant'});root.style.scrollBehavior=old;}};
 if(document.readyState==='complete')requestAnimationFrame(restore);else window.addEventListener('load',()=>requestAnimationFrame(()=>requestAnimationFrame(restore)),{once:true});
})();
