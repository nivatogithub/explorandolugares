(async () => {
  'use strict';
  const header = document.querySelector('header');
  if (!header || document.querySelector('.preprod-account-entry')) return;
  const languages = header.querySelector('.language-switch');
  if (!languages) return;
  const locale = document.documentElement.lang.startsWith('pt') ? 'pt-br' : document.documentElement.lang.startsWith('en') ? 'en' : 'es';
  const copy = {es: ['Iniciar sesión', 'Cerrar sesión', 'Mi cuenta', 'Cuenta de negocio'], en: ['Sign in', 'Sign out', 'My account', 'Business account'], 'pt-br': ['Iniciar sessão', 'Sair', 'Minha conta', 'Conta comercial']}[locale];
  const column = document.createElement('div');
  column.className = 'preprod-account-column';
  languages.before(column);
  column.append(languages);
  const bar = document.createElement('div');
  bar.setAttribute('role', 'group');
  bar.className = 'preprod-account-entry';
  bar.setAttribute('aria-label', copy[3]);
  column.append(bar);
  const signIn = () => {
    const link = document.createElement('a');
    link.textContent = copy[0];
    link.href = '/negocios/acceso/' + (locale === 'es' ? '' : '?lang=' + locale);
    bar.replaceChildren(link);
  };
  signIn();
  try {
    const response = await fetch('/__preprod-account.php', {credentials: 'same-origin', cache: 'no-store'});
    if (!response.ok) return;
    const account = await response.json();
    if (!account.authenticated) return;
    const name = document.createElement('a');
    name.className = 'preprod-account-name';
    name.textContent = account.name;
    name.href = '/negocios/portal/';
    name.setAttribute('aria-label', copy[2] + ': ' + account.name);
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '/negocios/salir/';
    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = 'csrf';
    csrf.value = account.csrf;
    const logout = document.createElement('button');
    logout.type = 'submit';
    logout.textContent = copy[1];
    form.append(csrf, logout);
    bar.replaceChildren(name, form);
  } catch { signIn(); }
})();
