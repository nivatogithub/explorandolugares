(async () => {
  'use strict';
  if(document.querySelector('.account-entry'))return;
  const header=document.querySelector('header');
  const existing=document.querySelector('.portal-account-strip');
  if(!header&&!existing)return;
  const locale = document.documentElement.lang.startsWith('pt') ? 'pt-br' : document.documentElement.lang.startsWith('en') ? 'en' : 'es';
  const copy = {es: ['Iniciar sesión', 'Cerrar sesión', 'Mi cuenta', 'Tu cuenta'], en: ['Sign in', 'Sign out', 'My account', 'Your account'], 'pt-br': ['Iniciar sessão', 'Sair', 'Minha conta', 'Sua conta']}[locale];
  const languages=header?.querySelector('.language-switch');
  const strip=existing||document.createElement('span');strip.className='portal-account-strip';strip.setAttribute('aria-label',copy[3]);
  const bar=existing?.querySelector('.portal-account-strip-inner')||document.createElement('span');bar.className='portal-account-strip-inner account-entry';
  if(!existing)strip.append(bar);
  if(languages){strip.classList.add('portal-account-inline');languages.prepend(strip);languages.classList.add('language-switch-with-account');}
  else if(!existing){strip.classList.add('portal-account-fallback');header.before(strip);}

  const modalCopy = {es: ['Correo electrónico','Contraseña','Entrar','Olvidé mi contraseña','¿No tienes cuenta?','Crear cuenta','Cerrar','Preparando acceso…','No se pudo iniciar sesión. Inténtalo de nuevo.'],en: ['Email','Password','Sign in','Forgot password?','No account yet?','Create account','Close','Preparing sign-in…','Unable to sign in. Please try again.'],'pt-br': ['E-mail','Senha','Entrar','Esqueceu sua senha?','Ainda não tem conta?','Cadastre-se','Fechar','Preparando acesso…','Não foi possível entrar. Tente novamente.']}[locale];
  async function showLogin(trigger, onSuccess) {
    if (document.querySelector('.account-login-dialog')) return;
    const dialog = document.createElement('dialog');
    dialog.className = 'account-login-dialog';
    dialog.setAttribute('aria-labelledby', 'account-login-title');
    dialog.innerHTML = `<button type="button" class="account-login-close" aria-label="${modalCopy[6]}">×</button><img class="account-login-logo" src="/assets/brand/explorando-lugares-logo-v2-transparent.png" alt="Explorando Lugares" width="80" height="80"><h2 id="account-login-title">${copy[0]}</h2><form method="post" action="/negocios/acceso/" class="account-login-form"><input type="hidden" name="csrf"><label>${modalCopy[0]}<input type="email" name="email" maxlength="254" autocomplete="username" required></label><label>${modalCopy[1]}<input type="password" name="password" autocomplete="current-password" required></label><p class="account-login-status" role="status" aria-live="polite">${modalCopy[7]}</p><button type="submit" disabled>${modalCopy[2]}</button></form><a href="/negocios/recuperar-clave/">${modalCopy[3]}</a><p>${modalCopy[4]} <a href="/negocios/registro/">${modalCopy[5]}</a></p>`;
    const form = dialog.querySelector('form');
    const submit = form.querySelector('button[type=submit]');
    const status = dialog.querySelector('[role=status]');
    const csrf = form.elements.csrf;
    const loginURL = '/negocios/acceso/' + (locale === 'es' ? '' : '?lang=' + locale);
    const parse = html => new DOMParser().parseFromString(html, 'text/html');
    dialog.querySelector('.account-login-close').addEventListener('click', () => dialog.close());
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
        if (response.ok) {
          const stateResponse=await fetch('/api/account-session.php',{credentials:'same-origin',cache:'no-store'});
          const state=stateResponse.ok?await stateResponse.json():null;
          if(state?.authenticated){await refreshAccount();dialog.close();if(typeof onSuccess==='function')onSuccess();return;}
        }
        const page = parse(await response.text());
        status.textContent = page.querySelector('[role=alert]')?.textContent || modalCopy[8];
        csrf.value = page.querySelector('input[name=csrf]')?.value || csrf.value;
      } catch { status.textContent=modalCopy[8]; }
      finally { submit.disabled=false; }
    });
  }
  document.addEventListener('el:request-login',event=>showLogin(event.detail?.trigger,event.detail?.onSuccess));
  const signIn = () => {
    const link = document.createElement('a');
    link.textContent = copy[0];
    link.href = '/negocios/acceso/' + (locale === 'es' ? '' : '?lang=' + locale);
    link.addEventListener('click', e => {e.preventDefault();showLogin(link);});
    bar.replaceChildren(link);
  };
  async function refreshAccount() {
  try {
    const response = await fetch('/api/account-session.php', {credentials: 'same-origin', cache: 'no-store'});
    if(!response.ok){if(!existing)strip.remove();return;}
    const account = await response.json();
    if(account.enabled===false){if(!existing)strip.remove();return;}
    if (!account.authenticated) {if(!existing?.hasAttribute('data-admin-legacy'))signIn();return;}
    const menu=document.createElement('details');menu.className='account-user-menu';
    const name=document.createElement('summary');name.className='account-name';name.textContent=account.name;
    name.setAttribute('aria-label',copy[3]+': '+account.name);menu.append(name);
    const menuPanel=document.createElement('div');menuPanel.className='account-user-menu-panel';
    menu.append(menuPanel);
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
    const myAccount=document.createElement('a');myAccount.href='/negocios/portal/';myAccount.textContent=copy[2];
    const actions=[menu,myAccount];
    if(account.administrator){const admin=document.createElement('a');admin.href='/admin/index.php';admin.textContent=locale==='en'?'Administration':locale==='pt-br'?'Administração':'Administración';actions.push(admin);}
    const help=document.createElement('a');help.href='/contacto.php';help.textContent=locale==='en'?'Contact and help':locale==='pt-br'?'Contato e ajuda':'Contacto y ayuda';menuPanel.append(help,form);bar.replaceChildren(...actions);
  } catch { signIn(); }
  }
  await refreshAccount();
  window.addEventListener('pageshow', e => {if(e.persisted)refreshAccount();});
})();
