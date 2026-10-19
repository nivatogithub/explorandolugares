/* Revalidate public language HTML before navigating; preserve the clean URL.
   This does not execute the fetched page or emit analytics events. */
(()=>{
 document.addEventListener('click',async event=>{
  if(event.defaultPrevented||event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
  const link=event.target.closest?.('.header-language-options a[href]');
  if(!link||link.target&&link.target!=='_self'||link.hasAttribute('download'))return;
  const url=new URL(link.href,location.href);if(url.origin!==location.origin)return;
  event.preventDefault();const controls=link.closest('.header-language-options');if(controls?.getAttribute('aria-busy')==='true')return;
  controls?.setAttribute('aria-busy','true');const controller=new AbortController();const timer=setTimeout(()=>controller.abort(),5000);
  try{const response=await fetch(url.href,{cache:'reload',credentials:'same-origin',signal:controller.signal});await response.arrayBuffer();}catch{}finally{clearTimeout(timer);location.assign(url.href);}
 });
})();
