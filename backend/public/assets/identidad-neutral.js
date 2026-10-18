/* Shared visual identity v2; content changes scoped to Jericó. */
(()=>{
 document.querySelectorAll('.pilot-banner').forEach(n=>n.remove());
 if(!/\/(?:destinos\/)?jerico(?:\/|\.html|$)/.test(location.pathname))return;
 const texts={"es": {"title": "Historia en breve", "history": "Fundado en 1850, Jericó creció entre caminos de arrieros, comercio y café. Sus casas y balcones conservan esa memoria. El centro histórico fue reconocido como patrimonio cultural nacional en 2018.", "intro": "Recorre sus calles y miradores, descubre el carriel y disfruta su cultura cafetera."}, "en": {"title": "A brief history", "history": "Founded in 1850, Jericó grew around mule routes, trade and coffee. Its houses and balconies preserve that heritage. The historic centre received national cultural heritage recognition in 2018.", "intro": "Explore its streets and viewpoints, discover carriel craftsmanship and enjoy its coffee culture."}, "pt-BR": {"title": "História em breve", "history": "Fundado em 1850, Jericó cresceu entre caminhos de tropeiros, comércio e café. Suas casas e varandas preservam essa memória. O centro histórico foi reconhecido como patrimônio cultural nacional em 2018.", "intro": "Explore ruas e mirantes, descubra o carriel e aproveite sua cultura cafeeira."}};const lang=document.documentElement.lang.toLowerCase();const l=lang.startsWith('en')?'en':lang.startsWith('pt')?'pt-BR':'es';const t=texts[l];
 document.body.classList.add('destination-direct-reading');
 const intro=document.querySelector('.destination-hero .intro');if(intro)intro.textContent=t.intro;
 const section=document.querySelector('#descubre');if(!section)return;
 section.querySelectorAll('.destination-history-photo,.destination-reasons').forEach(n=>n.remove());
 const story=section.querySelector(':scope>div');if(story){story.querySelectorAll(':scope>p:not(.credit)').forEach(n=>n.remove());const h=story.querySelector('h2');if(h){h.textContent=t.title;const p=document.createElement('p');p.textContent=t.history;h.after(p);}}
 document.querySelectorAll('a[href="#descubre"]').forEach(a=>a.textContent=l==='en'?'History':l==='pt-BR'?'História':'Historia');
 document.querySelector('.destination-business-link')?.remove();
 const compactData={"locales": ["es", "en", "pt-BR"], "cards": {"centro-historico": ["Parque principal, balcones y calles tradicionales.", "Main square, balconies and traditional streets.", "Praça principal, varandas e ruas tradicionais."], "cerro-el-salvador": ["Mirador del pueblo y las montañas.", "Views over the town and mountains.", "Mirante da cidade e das montanhas."], "santuario-de-santa-laura": ["Iglesia rosada del patrimonio religioso local.", "Pink church, part of the local religious heritage.", "Igreja rosa do patrimônio religioso local."], "casa-natal-de-santa-laura": ["Casa natal de Laura Montoya.", "Laura Montoya’s birthplace.", "Casa natal de Laura Montoya."], "jardin-botanico-los-balsos": ["Senderos, vegetación y aves cerca del centro.", "Paths, greenery and birds near the town centre.", "Trilhas, vegetação e aves perto do centro."], "subida-al-cerro-de-las-nubes": ["Sendero de montaña con miradores; confirma el acceso.", "Mountain trail with viewpoints; confirm access.", "Trilha de montanha com mirantes; confirme o acesso."], "chorros-de-quebradona": ["Cascadas rurales; confirma el recorrido y acceso local.", "Rural waterfalls; confirm the route and local access.", "Cachoeiras rurais; confirme o percurso e o acesso."], "helicoptero-medellin-jerico": ["Sobrevuela las montañas; confirma ruta y disponibilidad.", "Fly over the mountains; confirm route and availability.", "Sobrevoe as montanhas; confirme rota e disponibilidade."], "parapente-puerto-arturo": ["Vuelo biplaza; sujeto al clima y confirmación del operador.", "Tandem flight; subject to weather and operator confirmation.", "Voo duplo; sujeito ao clima e à confirmação do operador."], "experiencia-finca-cafetera": ["Conoce el cultivo y la preparación del café en una finca.", "Discover coffee growing and preparation on a farm.", "Conheça o cultivo e o preparo do café em uma fazenda."]}, "titles": {"es": ["Qué conocer", "Experiencias", "Directorio"], "en": ["Places to visit", "Experiences", "Directory"], "pt-BR": ["O que conhecer", "Experiências", "Diretório"]}};
 function compactJerico(){
  const sections=[document.querySelector('#que-conocer'),document.querySelector('.destination-experiences'),document.querySelector('#lugares')];
  for(const [i,area] of sections.entries()){
   if(!area)continue;
   for(const details of [...area.querySelectorAll('details.place-disclosure')].reverse()){
    const summary=details.querySelector(':scope>summary');const content=details.querySelector(':scope>.place-disclosure-content');
    if(summary)details.before(...summary.childNodes);if(content)details.before(...content.childNodes);details.remove();
   }
   const head=area.querySelector(':scope>.sectionhead,:scope>.destination-experiences-heading');
   if(head){head.classList.add('compact-section-heading');head.querySelectorAll('p').forEach(n=>n.remove());const old=head.querySelector('h2,h3');if(old){const h=document.createElement('h2');h.id=old.id;h.textContent=compactData.titles[l][i];old.replaceWith(h);}}
  }
  const idx=compactData.locales.indexOf(l);
  const keys=Object.keys(compactData.cards);const places=[...document.querySelectorAll('#atractivos-destino article')];const activities=[...document.querySelectorAll('.destination-experience-card')];
  if(places.length===7)places.forEach((c,i)=>c.dataset.placeSlug=keys[i]);if(activities.length===3)activities.forEach((c,i)=>c.dataset.experienceSlug=keys[i+7]);
  for(const card of document.querySelectorAll('#atractivos-destino article,.destination-experience-card')){
   card.hidden=false;const slug=card.dataset.placeSlug||card.dataset.experienceSlug;const v=compactData.cards[slug];const p=card.querySelector(':scope>p,.destination-experience-copy>p');if(p&&v)p.textContent=v[idx];
   card.querySelectorAll('.destination-experience-copy>.story-number').forEach(n=>n.remove());
  }
  document.querySelector('#cargar-mas-atractivos')?.remove();
  const invitation=document.querySelector('.destination-invitation');if(invitation&&!invitation.dataset.compact){const h=invitation.querySelector('h2');const button=invitation.querySelector('a.pill');if(h&&button){h.textContent=l==='en'?'Have a business in Jericó?':l==='pt-BR'?'Tem um negócio em Jericó?':'¿Tienes un negocio en Jericó?';const p=document.createElement('p');p.textContent=l==='en'?'Leave your details to join the directory.':l==='pt-BR'?'Deixe seus dados para participar do diretório.':'Déjanos tus datos para participar en el directorio.';const copy=document.createElement('div');copy.append(h,p);invitation.replaceChildren(copy,button);invitation.dataset.compact='true';}}
  document.querySelectorAll('#lugares .directory-description,#lugares .directory-card-details').forEach(n=>n.remove());
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',compactJerico,{once:true});else compactJerico();
 window.addEventListener('load',compactJerico,{once:true});
 const observer=new MutationObserver(records=>{if(records.some(r=>[...r.addedNodes].some(n=>n.nodeType===1&&(n.matches('details.place-disclosure')||n.querySelector('details.place-disclosure')))))compactJerico();});observer.observe(document.querySelector('main')||document.body,{childList:true,subtree:true});

})();
