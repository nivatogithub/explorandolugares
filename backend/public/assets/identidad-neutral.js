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
})();
