(() => {
  'use strict';
  const header = document.querySelector('header');
  if (!header || document.querySelector('.preprod-account-entry')) return;
  const languages = header.querySelector('.language-switch');
  if (!languages) return;
  const column = document.createElement('div');
  column.className = 'preprod-account-column';
  languages.before(column);
  column.append(languages);
  const bar = document.createElement('div');
  bar.setAttribute('role', 'group');
  bar.className = 'preprod-account-entry';
  bar.setAttribute('aria-label', 'Cuenta de negocio');
  for (const [label, url] of [['Crear cuenta', '/negocios/registro/'], ['Iniciar sesión', '/negocios/acceso/'], ['Mi cuenta', '/negocios/portal/']]) {
    const link = document.createElement('a');
    link.textContent = label;
    link.href = url;
    bar.append(link);
  }
  column.append(bar);
})();
