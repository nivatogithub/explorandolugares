/* Browser-side read-only semantic snapshot, usable on initial parsed HTML and live DOM. */
function presentationStructure(doc, live = false) {
 const norm = value => String(value || '').replace(/\s+/g, ' ').trim();
 const scope = el => {
  const card=el.closest('[data-place-slug],[data-experience-slug],[data-business-id]');
  if(card)return Object.entries(card.dataset).filter(([k])=>['placeSlug','experienceSlug','businessId'].includes(k)).map(([k,v])=>k+':'+v).join('|');
  const section=el.closest('section[id],main[id],aside.admin-sidebar');
  return section?.id || (section?.matches('aside')?'admin-navigation':el.closest('header')?'public-header':el.closest('footer')?'public-footer':'document');
 };
 const attrs=(el,names)=>Object.fromEntries(names.filter(k=>el.hasAttribute(k)).map(k=>[k,el.getAttribute(k)]));
 const all=selector=>[...doc.querySelectorAll(selector)];
 const text=el=>norm(el.textContent);
 const controls=form=>[...form.querySelectorAll('input,select,textarea,button')].map(el=>({tag:el.tagName.toLowerCase(),...attrs(el,['name','type','required','disabled','min','max','minlength','maxlength','pattern','accept','multiple','autocomplete','aria-label','aria-describedby']),label:text(el.closest('label')||el),options:el.tagName==='SELECT'?[...el.options].map(o=>({value:o.value,text:text(o),disabled:o.disabled})):undefined}));
 const textNodes=[];const walker=doc.createTreeWalker(doc.body||doc,NodeFilter.SHOW_TEXT);
 while(walker.nextNode()){const n=walker.currentNode;if(!n.parentElement?.closest('script,style,noscript')){const t=norm(n.textContent);if(t)textNodes.push(t)}}
 return {
  title:doc.title,language:doc.documentElement.lang,
  shell:{headers:all('header[data-portal-shell]').length,footers:all('footer[data-portal-footer]').length,adminMenus:all('aside.admin-sidebar').length},
  headings:all('h1,h2,h3,h4,h5,h6').map(el=>({tag:el.tagName,id:el.id,text:text(el),scope:scope(el)})),
  sections:all('main,section,article,aside,nav,details,dialog').map(el=>({tag:el.tagName,id:el.id,...attrs(el,['class','aria-label','aria-labelledby','data-place-slug','data-experience-slug','data-business-id']),scope:scope(el)})),
  textNodes,
  links:all('a[href]').map(el=>({...attrs(el,['id','href','target','rel','download','aria-label','aria-describedby','title']),text:text(el),scope:scope(el)})),
  forms:all('form').map(el=>({...attrs(el,['id','action','method','enctype','aria-label']),scope:scope(el),controls:controls(el)})),
  buttons:all('button,input[type=submit],summary').map(el=>({tag:el.tagName,...attrs(el,['id','name','type','aria-label','aria-controls','aria-expanded','disabled']),text:text(el),scope:scope(el)})),
  images:all('img').map(el=>({...attrs(el,['id','src','srcset','sizes','alt','width','height','loading','fetchpriority','draggable']),scope:scope(el),ancestorAction:el.closest('a')?.getAttribute('href')||null,...(live?{loaded:el.complete&&el.naturalWidth>0,currentSrc:el.currentSrc,naturalWidth:el.naturalWidth,naturalHeight:el.naturalHeight}:{})})),
  media:all('video,audio,source,iframe,object').map(el=>({tag:el.tagName,...attrs(el,['src','srcset','data','type','title','controls']),scope:scope(el)})),
  assets:all('script[src],link[rel=stylesheet],link[rel=preload]').map(el=>({...attrs(el,['src','href','rel','as','type','defer','async'])}))
 };
}
