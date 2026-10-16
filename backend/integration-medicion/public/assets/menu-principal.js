(() => {
  const nav = document.querySelector('header nav[aria-label]');
  if (!nav) return;
  if(!nav.querySelector('a[href*="cobertura/colombia"]')){const link=document.createElement('a');link.href='/cobertura/colombia/';link.textContent=document.documentElement.lang==='en'?'We’re growing':document.documentElement.lang==='pt-BR'?'Estamos crescendo':'Estamos creciendo';nav.insertBefore(link,nav.querySelector('.pill'));}
  const header = nav.closest('header');
  const button = document.createElement('button');
  const id = 'menu-principal';
  nav.id = id;
  button.type = 'button';
  button.className = 'menu-toggle';
  button.setAttribute('aria-controls', id);
  button.setAttribute('aria-expanded', 'false');
  button.setAttribute('aria-label', document.documentElement.lang === 'en' ? 'Open menu' : (document.documentElement.lang === 'pt-BR' ? 'Abrir menu' : 'Abrir menú'));
  button.innerHTML = '<span></span><span></span><span></span>';
  nav.before(button);
  const close = () => { header.classList.remove('menu-open'); button.setAttribute('aria-expanded', 'false'); };
  button.addEventListener('click', () => {
    const open = !header.classList.contains('menu-open');
    header.classList.toggle('menu-open', open);
    button.setAttribute('aria-expanded', String(open));
  });
  nav.addEventListener('click', event => { if (event.target.closest('a')) close(); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
})();

/* First-party opt-in analytics; standalone loader, no measurement before consent. */
(()=>{if(document.querySelector('script[data-el-measurement]'))return;const s=document.createElement('script');s.src='/assets/cobertura/medicion.js?v=1';s.defer=true;s.dataset.elMeasurement='1';document.head.append(s);})();
