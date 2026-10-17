(async () => {
  'use strict';
  const header = document.querySelector('header');
  if (!header || document.querySelector('.preprod-account-entry')) return;
  const locale = document.documentElement.lang.startsWith('pt') ? 'pt-br' : document.documentElement.lang.startsWith('en') ? 'en' : 'es';
  const copy = {es: ['Iniciar sesión', 'Cerrar sesión', 'Mi cuenta', 'Tu cuenta'], en: ['Sign in', 'Sign out', 'My account', 'Your account'], 'pt-br': ['Iniciar sessão', 'Sair', 'Minha conta', 'Sua conta']}[locale];
  const strip = document.createElement('div');strip.className='portal-account-strip';strip.setAttribute('aria-label',copy[3]);
  const bar = document.createElement('div');bar.className='portal-account-strip-inner preprod-account-entry';strip.append(bar);header.before(strip);
  const modalCopy = {es: ['Correo electrónico','Contraseña','Entrar','Olvidé mi contraseña','¿No tienes cuenta?','Crear cuenta','Cerrar','Preparando acceso…','No se pudo iniciar sesión. Inténtalo de nuevo.'],en: ['Email','Password','Sign in','Forgot password?','No account yet?','Create account','Close','Preparing sign-in…','Unable to sign in. Please try again.'],'pt-br': ['E-mail','Senha','Entrar','Esqueceu sua senha?','Ainda não tem conta?','Cadastre-se','Fechar','Preparando acesso…','Não foi possível entrar. Tente novamente.']}[locale];
  async function showLogin(trigger) {
    if (document.querySelector('.preprod-login-dialog')) return;
    const dialog = document.createElement('dialog');
    dialog.className = 'preprod-login-dialog';
    dialog.setAttribute('aria-labelledby', 'preprod-login-title');
    dialog.innerHTML = `<button type="button" class="preprod-login-close" aria-label="${modalCopy[6]}">×</button><h2 id="preprod-login-title">${copy[0]}</h2><form method="post" action="/negocios/acceso/" class="preprod-login-form"><input type="hidden" name="csrf"><label>${modalCopy[0]}<input type="email" name="email" maxlength="254" autocomplete="username" required></label><label>${modalCopy[1]}<input type="password" name="password" autocomplete="current-password" required></label><p class="preprod-login-status" role="status" aria-live="polite">${modalCopy[7]}</p><button type="submit" disabled>${modalCopy[2]}</button></form><a href="/negocios/recuperar-clave/">${modalCopy[3]}</a><p>${modalCopy[4]} <a href="/negocios/registro/">${modalCopy[5]}</a></p>`;
    const form = dialog.querySelector('form');
    const submit = form.querySelector('button[type=submit]');
    const status = dialog.querySelector('[role=status]');
    const csrf = form.elements.csrf;
    const loginURL = '/negocios/acceso/' + (locale === 'es' ? '' : '?lang=' + locale);
    const parse = html => new DOMParser().parseFromString(html, 'text/html');
    dialog.querySelector('.preprod-login-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', e => { if (e.target === dialog) { const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)dialog.close(); } });
    dialog.addEventListener('close', () => { dialog.remove();(document.contains(trigger) ? trigger : bar.querySelector('a,button'))?.focus(); });
    document.body.append(dialog);
    dialog.showModal();
    try {
      const response = await fetch(loginURL, {credentials:'same-origin',cache:'no-store'});
      const page = parse(await response.text());
      csrf.value = page.querySelector('input[name=csrf]')?.value || '';
      if (!response.ok || !csrf.value) throw new Error();
      status.textContent = '';
      submit.disabled = false;
      form.elements.email.focus();
    } catch { status.textContent = modalCopy[8]; }
    form.addEventListener('submit', async e => {
      e.preventDefault();submit.disabled=true;status.textContent=modalCopy[7];
      try {
        const response = await fetch(loginURL, {method:'POST',credentials:'same-origin',body:new URLSearchParams(new FormData(form))});
        if (response.ok && new URL(response.url).pathname === '/negocios/portal/') { await refreshAccount();dialog.close();return; }
        const page = parse(await response.text());
        status.textContent = page.querySelector('[role=alert]')?.textContent || modalCopy[8];
        csrf.value = page.querySelector('input[name=csrf]')?.value || csrf.value;
      } catch { status.textContent=modalCopy[8]; }
      finally { submit.disabled=false; }
    });
  }
  const signIn = () => {
    const link = document.createElement('a');
    link.textContent = copy[0];
    link.href = '/negocios/acceso/' + (locale === 'es' ? '' : '?lang=' + locale);
    link.addEventListener('click', e => {e.preventDefault();showLogin(link);});
    bar.replaceChildren(link);
  };
  async function refreshAccount() {
  try {
    const response = await fetch('/__preprod-account.php', {credentials: 'same-origin', cache: 'no-store'});
    if (!response.ok) return;
    const account = await response.json();
    if (!account.authenticated) {signIn();return;}
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
    form.addEventListener('submit', async e => {e.preventDefault();logout.disabled=true;try{const result=await fetch(form.action,{method:'POST',credentials:'same-origin',body:new URLSearchParams(new FormData(form))});if(result.ok)await refreshAccount();}finally{logout.disabled=false;}});
    form.append(csrf, logout);
    if(account.administrator){const admin=document.createElement('a');admin.href='/admin/index.php';admin.textContent=locale==='en'?'Administration':locale==='pt-br'?'Administração':'Administración';bar.replaceChildren(name,admin,form);}else bar.replaceChildren(name, form);
  } catch { signIn(); }
  }
  signIn();
  await refreshAccount();
  window.addEventListener('pageshow', e => {if(e.persisted)refreshAccount();});
})();
