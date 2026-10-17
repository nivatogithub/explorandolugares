/* Private Cartagena pilot: proposed illustrations, no approval or database changes. */
(() => {
 if (!document.body.classList.contains('el-review-rest')) return;
 const base='/assets/cartagena-piloto/';
 const names={'caminhada-patrimonial':'Caminata patrimonial','visita-muhca':'Visita a un museo: composición representativa, no sala real','bus-panoramico':'Recorrido en bus panorámico','buceo-rosario':'Buceo con equipo autónomo','snorkel-rosario':'Snorkel en superficie','catamaran-cartagena':'Navegación en catamarán','pesca-boquilla':'Recorrido en canoa entre manglares y pesca tradicional'};
 for (const card of document.querySelectorAll('[data-experience-slug]')) {
  const key=card.dataset.experienceSlug; if (!names[key]) continue;
  const frame=card.querySelector('.experience-art'); if(!frame) continue;
  const img=document.createElement('img'); img.src=base+key+'-480.webp'; img.srcset=base+key+'-480.webp 480w, '+base+key+'-800.webp 800w'; img.sizes='(max-width: 700px) 90vw, 400px'; img.alt='Ilustración propuesta: '+names[key]; img.loading='lazy'; img.decoding='async'; img.width=800; img.height=533;
  frame.removeAttribute('role'); frame.removeAttribute('aria-label'); frame.classList.add('is-painted'); frame.replaceChildren(img);
 }
 const places={'centro-historico':'Centro histórico y Torre del Reloj','san-felipe':'Castillo de San Felipe de Barajas','getsemani':'Getsemaní','islas-rosario':'Islas del Rosario','playa-blanca-baru':'Playa Blanca, Barú','cerro-popa':'Claustro del convento de La Popa','san-pedro-claver':'Iglesia de San Pedro Claver','bocagrande':'Bocagrande'};
 const lang=document.documentElement.lang.toLowerCase();
 const prefix=lang.startsWith('en')?'Proposed illustration: ':lang.startsWith('pt')?'Ilustração proposta: ':'Ilustración propuesta: ';
 function paintPlaces(){
 for(const card of document.querySelectorAll('#atractivos-destino > article[data-place-slug]')){
  const key=card.dataset.placeSlug; if(!places[key])continue;
  const frame=card.querySelector('.destination-place-caricature'); if(!frame || frame.classList.contains('is-painted'))continue;
  const img=document.createElement('img'); img.src=base+key+'-480.webp';img.srcset=base+key+'-480.webp 480w, '+base+key+'-800.webp 800w';img.sizes='(max-width: 700px) 90vw, 400px';img.alt=prefix+places[key];img.loading='lazy';img.decoding='async';img.width=800;img.height=533;
  const link=document.createElement('a');link.href=base+key+'-800.webp';link.target='_blank';link.rel='noopener';link.setAttribute('aria-label',prefix+places[key]);link.append(img);
  frame.removeAttribute('role');frame.removeAttribute('aria-label');frame.classList.add('is-painted');frame.replaceChildren(link);
  if(key==='san-felipe' && !card.querySelector('.pilot-source-credit')){const c=document.createElement('small');c.className='pilot-source-credit';const a=document.createElement('a');a.href='https://commons.wikimedia.org/wiki/File:Cartagena-Castillo_San_Felipe_de_Barajas.JPG';a.textContent='Ilustración IA sobre foto de JuanGris (Lucía Estévez), CC BY-SA 3.0';c.append(a);card.append(c);}
 }
 }
 paintPlaces();
 const grid=document.querySelector('#atractivos-destino');if(grid)new MutationObserver(paintPlaces).observe(grid,{childList:true});
 const hero=document.querySelector('.destination-hero figure'); const img=hero?.querySelector('img');
 if(img){img.src=base+'portada-1200.webp';img.srcset=base+'portada-640.webp 640w, '+base+'portada-1200.webp 1200w';img.sizes='(max-width: 700px) 90vw, 55vw';img.alt='Ilustración de Cartagena desde La Popa, basada en fotografía histórica de 2008';img.width=1200;img.height=800;
 const credit=document.createElement('figcaption');credit.textContent='Propuesta ilustrada con IA · referencia: Norma Gòmez, Cartagena desde La Popa (2008), CC BY 2.0. '; const a=document.createElement('a');a.href='https://commons.wikimedia.org/wiki/File:Cartagena_de_Indias_desde_el_cerro_La_Popa.jpg';a.textContent='Fotografía original';credit.append(a);hero.querySelectorAll('figcaption').forEach(n=>n.remove());hero.append(credit);}
})();
