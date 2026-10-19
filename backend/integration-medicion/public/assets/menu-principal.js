(() => {
  const nav = document.querySelector('header nav[aria-label]:not(.header-language-options)');
  if (!nav) return;
  if(!nav.querySelector('a[href*="cobertura/colombia"]')){const link=document.createElement('a');link.href='/cobertura/colombia/';link.textContent=document.documentElement.lang==='en'?'We’re growing':document.documentElement.lang==='pt-BR'?'Estamos crescendo':'Estamos creciendo';nav.insertBefore(link,nav.querySelector('.pill'));}
  const header = nav.closest('header');
  const button = header.querySelector('.menu-toggle') || document.createElement('button');
  const id = 'menu-principal';
  nav.id = id;
  button.type = 'button';
  button.className = 'menu-toggle';
  button.setAttribute('aria-controls', id);
  button.setAttribute('aria-expanded', 'false');
  button.setAttribute('aria-label', document.documentElement.lang === 'en' ? 'Open menu' : (document.documentElement.lang === 'pt-BR' ? 'Abrir menu' : 'Abrir menú'));
  button.innerHTML = '<span></span><span></span><span></span>';
  if(!button.isConnected)nav.before(button);
  const close = () => { header.classList.remove('menu-open'); button.setAttribute('aria-expanded', 'false'); };
  button.addEventListener('click', () => {
    const open = !header.classList.contains('menu-open');
    header.classList.toggle('menu-open', open);
    button.setAttribute('aria-expanded', String(open));
  });
  nav.addEventListener('click', event => { if (event.target.closest('a')) close(); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
})();

/* Shared loader: aggregate counters without visitor cookies plus optional consent-based analytics. */
(()=>{if(document.querySelector('script[data-el-measurement]'))return;const s=document.createElement('script');s.src='/assets/cobertura/medicion.js?v=ec5246223dc4';s.defer=true;s.dataset.elMeasurement='1';document.head.append(s);})();

(()=>{if(document.querySelector('script[src*="/assets/account-entry.js"]'))return;const css=document.createElement('link');css.rel='stylesheet';css.href='/assets/account-entry.css?v=3';document.head.append(css);const script=document.createElement('script');script.src='/assets/account-entry.js?v=4';script.defer=true;document.head.append(script);})();

// Preserve existing contact channels and add the consolidated help entry.
(()=>{const footer=document.querySelector('footer .wrap')||document.querySelector('footer');if(!footer||footer.querySelector('a[href="/contacto.php"]'))return;const link=document.createElement('a');link.href='/contacto.php';link.className='footer-help-link';link.textContent=document.documentElement.lang.startsWith('en')?'Contact and help':document.documentElement.lang.startsWith('pt')?'Contato e ajuda':'Contacto y ayuda';const group=document.createElement('span');group.append(link);footer.append(group);})();

// Compatibility for older pages; packaged pages already carry their header and CSS.
(()=>{if(document.querySelector('.header-language-bar'))return;const c=document.createElement('link');c.rel='stylesheet';c.href='/assets/header-controls.css?v=2';document.head.append(c);const s=document.createElement('script');s.src='/assets/header-controls.js';s.defer=true;document.head.append(s);})();
