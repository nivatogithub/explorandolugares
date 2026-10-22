(async () => {
  'use strict';
  if(document.querySelector('.account-entry'))return;
  const header=document.querySelector('header');
  const existing=document.querySelector('.portal-account-strip');
  if(!header&&!existing)return;
  if(!document.querySelector('link[data-account-page-style],link[href*="/assets/account-page.css"]')){const style=document.createElement('link');style.rel='stylesheet';style.href='/assets/account-page.css';style.dataset.accountPageStyle='';document.head.append(style);}
  const locale = document.documentElement.lang.startsWith('pt') ? 'pt-br' : document.documentElement.lang.startsWith('en') ? 'en' : 'es';
  const copy = {es: ['Iniciar sesión', 'Cerrar sesión', 'Mi cuenta', 'Tu cuenta'], en: ['Log in', 'Sign out', 'My account', 'Your account'], 'pt-br': ['Iniciar sessão', 'Sair', 'Minha conta', 'Sua conta']}[locale];
  const languages=header?.querySelector('.language-switch');
  const strip=existing||document.createElement('span');strip.className='portal-account-strip';strip.setAttribute('aria-label',copy[3]);
  const bar=existing?.querySelector('.portal-account-strip-inner')||document.createElement('span');bar.className='portal-account-strip-inner account-entry';
  if(!existing)strip.append(bar);
  if(languages){strip.classList.add('portal-account-inline');languages.prepend(strip);languages.classList.add('language-switch-with-account');}
  else if(!existing){strip.classList.add('portal-account-fallback');header.before(strip);}

  const modalCopy = {es: ['Correo electrónico','Contraseña','Entrar','Olvidé mi contraseña','¿No tienes cuenta?','Crear cuenta','Cerrar','Preparando acceso…','No se pudo iniciar sesión. Inténtalo de nuevo.'],en: ['Email','Password','Sign in','Forgot password?','No account yet?','Create account','Close','Preparing sign-in…','Unable to sign in. Please try again.'],'pt-br': ['E-mail','Senha','Entrar','Esqueceu sua senha?','Ainda não tem conta?','Cadastre-se','Fechar','Preparando acesso…','Não foi possível entrar. Tente novamente.']}[locale];
  const authPath = url => /^\/(?:negocios\/(?:acceso|registro|recuperar-clave|cambiar-clave)\/?|cuenta\/verificar-correo\/?)$/.test(url.pathname) && !(url.pathname.includes('registro') && url.searchParams.get('tipo')==='business');
  const parse = html => new DOMParser().parseFromString(html,'text/html');
  let authSuccess=null, authTrigger=null;
  async function showLogin(trigger,onSuccess,url='/negocios/acceso/') {
    authSuccess=onSuccess;authTrigger=trigger;
    let dialog=document.querySelector('[data-account-auth-dialog]');
    if(!dialog){dialog=document.createElement('dialog');dialog.className='account-login-dialog account-auth-dialog';dialog.dataset.accountAuthDialog='';dialog.setAttribute('aria-label',copy[3]);dialog.innerHTML='<button type="button" class="account-login-close" aria-label="Cerrar">×</button><div data-account-auth-content role="status">Cargando…</div>';document.body.append(dialog);}
    if(!dialog.open)dialog.showModal();
    try{const response=await fetch(url,{credentials:'same-origin',cache:'no-store'});await renderAuth(response,dialog);}
    catch{dialog.querySelector('[data-account-auth-content]').textContent=modalCopy[8];}
  }
  async function renderAuth(response,dialog){
    const page=parse(await response.text()),content=page.querySelector('[data-account-auth-content]');
    if(!content)throw new Error('No account content');
    dialog.querySelector('[data-account-auth-content]').replaceChildren(...Array.from(content.childNodes));
    const form=dialog.querySelector('form');if(form)form.action=response.url;
    dialog.dataset.authUrl=response.url;
    if(!document.querySelector('script[data-auth-residence]')&&!document.querySelector('script[src*="account-residence.js"]')){const script=document.createElement('script');script.src='/assets/account-residence.js';script.dataset.authResidence='';document.head.append(script);}
    document.dispatchEvent(new CustomEvent('el:auth-render'));
    dialog.querySelector('input:not([type=hidden]),button[type=submit],a')?.focus();
  }
  document.addEventListener('click',event=>{
    const close=event.target.closest('[data-account-auth-dialog] .account-login-close');
    if(close){const dialog=close.closest('dialog');dialog.close();dialog.remove();if(document.body.classList.contains('portal-account-page')&&authPath(new URL(location.href)))location.assign('/');else authTrigger?.focus();return;}
    const link=event.target.closest('a[href]');if(!link||event.defaultPrevented||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey||link.target==='_blank')return;
    const url=new URL(link.href,location.href);if(url.origin!==location.origin||!authPath(url)||url.searchParams.has('token'))return;
    event.preventDefault();showLogin(link,null,url.href);
  });
  document.addEventListener('submit',async event=>{
    const form=event.target,dialog=form.closest('[data-account-auth-dialog]');if(!dialog)return;
    event.preventDefault();if(form.dataset.busy)return;form.dataset.busy='1';
    const submit=form.querySelector('[type=submit]');if(submit)submit.disabled=true;
    try{
      const target=form.getAttribute('action')||dialog.dataset.authUrl||location.href;
      const response=await fetch(target,{method:'POST',credentials:'same-origin',body:new URLSearchParams(new FormData(form))});
      if(new URL(target,location.href).pathname.includes('/acceso/')&&response.ok){
        const stateResponse=await fetch('/api/account-session.php',{credentials:'same-origin',cache:'no-store'});const state=stateResponse.ok?await stateResponse.json():null;
        if(state?.authenticated){await refreshAccount();dialog.close();dialog.remove();if(typeof authSuccess==='function')authSuccess();else if(document.body.classList.contains('portal-account-page')||new URL(response.url).pathname.includes('/suscripciones/'))location.assign(response.url);return;}
      }
      await renderAuth(response,dialog);
    }catch{let status=form.querySelector('[role=alert]');if(!status){status=document.createElement('p');status.setAttribute('role','alert');form.append(status);}status.textContent=modalCopy[8];}
    finally{delete form.dataset.busy;if(submit)submit.disabled=false;}
  });
  const initialDialog=document.querySelector('[data-account-auth-dialog]');
  if(initialDialog){initialDialog.dataset.authUrl=location.href;initialDialog.removeAttribute('open');initialDialog.showModal();initialDialog.addEventListener('cancel',()=>location.assign('/'));}
  document.addEventListener('el:request-login',event=>showLogin(event.detail?.trigger,event.detail?.onSuccess));
  const signIn = () => {
    const link = document.createElement('a');
    link.textContent = copy[0];
    link.href = '/negocios/acceso/' + ('?lang=' + locale);
    const register=document.createElement('a');register.href='/negocios/registro/'+('?lang='+locale);register.textContent=locale==='en'?'Sign up':locale==='pt-br'?'Cadastre-se':'Registrarse';register.className='header-register';bar.replaceChildren(link,register);
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
    form.addEventListener('submit', async e => {e.preventDefault();logout.disabled=true;try{const result=await fetch(form.action,{method:'POST',credentials:'same-origin',body:new URLSearchParams(new FormData(form))});if(result.ok){await refreshAccount();if(document.body.classList.contains('portal-account-page')||location.pathname.startsWith('/admin'))location.assign('/');}}finally{logout.disabled=false;}});
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
