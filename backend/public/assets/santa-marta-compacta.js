/* Santa Marta private review only. Load after destination markup; no database writes. */
(async()=>{
 const script=document.currentScript;
 const slug=document.body.dataset.destinationSlug;
 const route=location.pathname.split('/').filter(Boolean);
 if(slug!=='santa-marta'&&!route.includes('santa-marta'))return;
 const assetBase=new URL('santa-marta-semirrealista/',script.src);
 const response=await fetch(new URL('contenido.json',assetBase));
 if(!response.ok)throw new Error('Santa Marta review content unavailable');
 const data=await response.json();
 if(data.destination!=='santa-marta'||data.places.length!==8||data.experiences.length!==9)throw new Error('Unexpected Santa Marta review package');
 const language=document.documentElement.lang.toLowerCase();
 const locale=language.startsWith('en')?'en':language.startsWith('pt')?'pt-BR':'es';
 const copy=data.translations[locale];
 document.body.classList.add('santa-marta-compacta');
 const image=(asset,name,hero=false)=>{
  const img=document.createElement('img');img.src=new URL(asset+'-'+(hero?1200:800)+'.webp',assetBase);
  img.srcset=new URL(asset+'-'+(hero?640:480)+'.webp',assetBase)+' '+(hero?640:480)+'w, '+img.src+' '+(hero?1200:800)+'w';
  img.sizes=hero?'(max-width: 760px) 100vw, 55vw':'(max-width: 650px) 100vw, (max-width: 1050px) 50vw, 33vw';
  img.alt=copy.image_label+': '+name;img.width=hero?1200:800;img.height=hero?800:533;img.decoding='async';
  if(hero)img.fetchPriority='high';else img.loading='lazy';
  return img;
 };
 const intro=document.querySelector('.destination-hero .intro');if(intro)intro.textContent=copy.intro;
 const em=document.querySelector('.destination-hero h1 em');if(em)em.textContent=copy.headline;
 const figure=document.querySelector('.destination-hero figure');
 if(figure){const caption=document.createElement('figcaption');caption.className='credit';caption.textContent=copy.image_label;figure.replaceChildren(image('hero-v2','Santa Marta · bahía histórica y Camellón',true),caption);}
 const overview=document.querySelector('#descubre')||document.querySelector('.destination-overview');
 if(overview){overview.classList.add('sm-history');const text=document.createElement('div');const heading=document.createElement('h2');heading.textContent=copy.history_title;const p=document.createElement('p');p.textContent=copy.history;text.append(heading,p);overview.replaceChildren(text);}
 document.querySelectorAll('.destination-hero .eyebrow,.pilot-banner,.destination-business-link').forEach(n=>n.remove());
 const banner=document.querySelector('.destination-preview-banner');if(banner)banner.textContent=copy.draft;
 const invitation=document.querySelector('.destination-invitation');
 if(invitation){const button=invitation.querySelector('a.pill');if(button){const content=document.createElement('div');const h=document.createElement('h2');h.textContent=copy.business_title;const p=document.createElement('p');p.textContent=copy.business_text;content.append(h,p);invitation.replaceChildren(content,button);}}
 document.querySelectorAll('a[href="#descubre"]').forEach(a=>a.textContent=locale==='en'?'History':locale==='pt-BR'?'História':'Historia');
 const practical=document.querySelector('.destination-practical details:first-child p');if(practical)practical.textContent=copy.practical;
 function heading(section,title){
  let head=section.querySelector(':scope>.sectionhead,:scope>.destination-experiences-heading');
  if(!head){head=document.createElement('div');head.className='destination-experiences-heading';section.prepend(head);}
  const h=document.createElement('h2');h.textContent=title;head.replaceChildren(h);head.classList.add('compact-section-heading');
 }
 function link(row){if(!row.source_url)return null;const a=document.createElement('a');a.className='text-link';a.href=row.source_url;a.target='_blank';a.rel='noopener noreferrer';a.textContent=copy.source_label;return a;}
 function pager(grid){
  grid.nextElementSibling?.matches('.row-load-more')&&grid.nextElementSibling.remove();
  const cards=[...grid.children];let rows=2;
  const button=document.createElement('button');button.type='button';button.className='destination-load-more sm-load-more';
  button.textContent=locale==='en'?'Load more':locale==='pt-BR'?'Carregar mais':'Cargar más';
  if(!grid.id)grid.id='sm-experience-grid';button.setAttribute('aria-controls',grid.id);grid.after(button);
  const refresh=()=>{const n=Math.max(1,getComputedStyle(grid).gridTemplateColumns.trim().split(/\s+/).length);cards.forEach((c,i)=>c.hidden=i>=rows*n);button.hidden=rows*n>=cards.length;};
  button.onclick=()=>{rows+=2;refresh();};window.addEventListener('resize',refresh);refresh();
 }
 const placesSection=document.querySelector('#que-conocer');let grid=placesSection?.querySelector('.highlight-grid');
 if(placesSection){heading(placesSection,copy.places_title);if(!grid){grid=document.createElement('div');grid.className='highlight-grid';grid.id='atractivos-destino';placesSection.append(grid);}
 grid.replaceChildren(...data.places.map(row=>{const t=row.translations[locale],card=document.createElement('article');card.dataset.placeSlug=row.id;const photo=document.createElement('a');photo.className='highlight-photo';photo.href=new URL(row.asset_id+'-800.webp',assetBase);photo.target='_blank';photo.rel='noopener';photo.append(image(row.asset_id,t.name));const h=document.createElement('h3');h.textContent=t.name;const p=document.createElement('p');p.textContent=t.description;card.append(photo,h,p);const a=link(row);if(a)card.append(a);return card;}));}
 let section=document.querySelector('.destination-experiences');
 if(!section&&placesSection){section=document.createElement('section');section.className='destination-experiences';placesSection.append(section);}
 if(section){heading(section,copy.experiences_title);let list=section.querySelector('.destination-experience-list');if(!list){list=document.createElement('div');list.className='destination-experience-list';section.append(list);}
 list.replaceChildren(...data.experiences.map(row=>{const t=row.translations[locale],card=document.createElement('article');card.className='destination-experience-card';card.dataset.experienceSlug=row.id;card.dataset.locationId=row.location_id||'';const text=document.createElement('div');text.className='destination-experience-copy';const h=document.createElement('h4');h.textContent=t.name;const p=document.createElement('p');p.textContent=t.description;text.append(h,p);const a=link(row);if(a)text.append(a);card.append(image(row.asset_id,t.name),text);return card;}));}
 const directory=document.querySelector('#lugares');if(directory)heading(directory,copy.directory_title);
 document.querySelector('#cargar-mas-atractivos')?.remove();
 // Existing third-party credits outside replaced images are preserved.
 if(document.readyState==='complete'){if(grid)pager(grid);const list=section?.querySelector('.destination-experience-list');if(list)pager(list);}
 else window.addEventListener('load',()=>{if(grid)pager(grid);const list=section?.querySelector('.destination-experience-list');if(list)pager(list);},{once:true});
 document.dispatchEvent(new CustomEvent('santa-marta-review-ready',{detail:{places:8,experiences:9,locale}}));
})().catch(error=>console.error('Santa Marta private review:',error));
