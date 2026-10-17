(() => {
  'use strict';
  const header = document.querySelector('header');
  if (!header || document.querySelector('.preprod-account-entry')) return;
  const bar = document.createElement('nav');
  bar.className = 'preprod-account-entry';
  bar.setAttribute('aria-label', 'Cuenta de negocio');
  const content = document.createElement('div');
  content.className = 'wrap';
  const title = document.createElement('strong');
  title.textContent = 'Tu cuenta de negocio';
  content.append(title);
  for (const [label, url] of [['Crear cuenta', '/negocios/registro/'], ['Iniciar sesión', '/negocios/acceso/'], ['Mi cuenta', '/negocios/portal/']]) {
    const link = document.createElement('a');
    link.textContent = label;
    link.href = url;
    content.append(link);
  }
  bar.append(content);
  header.after(bar);
})();
