(() => {
  const nav = document.querySelector('header nav[aria-label]');
  if (!nav) return;
  const header = nav.closest('header');
  const button = document.createElement('button');
  const id = 'menu-principal';
  nav.id = id;
  button.type = 'button';
  button.className = 'menu-toggle';
  button.setAttribute('aria-controls', id);
  button.setAttribute('aria-expanded', 'false');
  button.setAttribute('aria-label', document.documentElement.lang === 'en' ? 'Open menu' : 'Abrir menú');
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
