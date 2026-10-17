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
 const hero=document.querySelector('.destination-hero figure'); const img=hero?.querySelector('img');
 if(img){img.src=base+'portada-1200.webp';img.srcset=base+'portada-640.webp 640w, '+base+'portada-1200.webp 1200w';img.sizes='(max-width: 700px) 90vw, 55vw';img.alt='Ilustración de Cartagena desde La Popa, basada en fotografía histórica de 2008';img.width=1200;img.height=800;
 const credit=document.createElement('figcaption');credit.textContent='Propuesta ilustrada con IA · referencia: Norma Gòmez, Cartagena desde La Popa (2008), CC BY 2.0. '; const a=document.createElement('a');a.href='https://commons.wikimedia.org/wiki/File:Cartagena_de_Indias_desde_el_cerro_La_Popa.jpg';a.textContent='Fotografía original';credit.append(a);hero.querySelectorAll('figcaption').forEach(n=>n.remove());hero.append(credit);}
})();
