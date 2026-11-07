(async () => {
  'use strict';
  function startResendCountdowns(root){
    for(const form of root.querySelectorAll('[data-resend-seconds]')){
      if(form.dataset.resendTimerStarted)continue;
      const seconds=Number(form.dataset.resendSeconds);
      const button=form.querySelector('button[type=submit]');
      const timer=form.querySelector('[data-resend-countdown]');
      if(!button||!timer||!Number.isFinite(seconds)||seconds<=0)continue;
      form.dataset.resendTimerStarted='1';
      const deadline=performance.now()+seconds*1000;
      function tick(){
        if(!form.isConnected)return;
        const remaining=Math.max(0,Math.ceil((deadline-performance.now())/1000));
        button.disabled=remaining>0;
        timer.hidden=remaining===0;
        if(remaining===0)return;
        const time=`${Math.floor(remaining/60)}:${String(remaining%60).padStart(2,'0')}`;
        timer.textContent=timer.dataset.resendCountdown.replace('{time}',time);
        setTimeout(tick,250);
      }
      tick();
    }
  }
  startResendCountdowns(document);
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
  let authSuccess=null, authTrigger=null, authCancel=null;
  const closeAuth = dialog => {
    const standalone = document.body.classList.contains('portal-account-page') && authPath(new URL(location.href));
    const destination = new URL(dialog.dataset.authReturn || '/', location.origin);
    dialog.close(); dialog.remove();
    if (typeof authCancel === 'function') authCancel();
    if (!standalone) { authTrigger?.focus({preventScroll:true}); return; }
    let previous;
    try { previous = new URL(document.referrer); } catch (_) {}
    if (previous?.origin === location.origin && !authPath(previous) && history.length > 1) {
      history.back(); // Browser restores the actual previous page, filters and scroll position.
    } else {
      location.assign(destination.origin === location.origin ? destination.href : '/');
    }
  };
  async function showLogin(trigger,onSuccess,url='/negocios/acceso/',onCancel=null) {
    const target=new URL(url,location.href);if(target.origin!==location.origin)return;
    authSuccess=onSuccess;authTrigger=trigger;authCancel=onCancel;
    let dialog=document.querySelector('[data-account-auth-dialog]');
    if(!dialog){dialog=document.createElement('dialog');dialog.className='account-login-dialog account-auth-dialog';dialog.dataset.accountAuthDialog='';dialog.setAttribute('aria-label',copy[3]);dialog.innerHTML='<button type="button" class="account-login-close" aria-label="Cerrar">×</button><div data-account-auth-content role="status">Cargando…</div>';document.body.append(dialog);dialog.addEventListener('cancel',event=>{event.preventDefault();closeAuth(dialog);});}
    if(!dialog.open)dialog.showModal();
    try{
      const response=await fetch(url,{credentials:'same-origin',cache:'no-store'});
      // A session can become active in another tab while the dialog request is in flight.
      if(!authPath(new URL(response.url))){
        const check=await fetch('/api/account-session.php',{credentials:'same-origin',cache:'no-store'});
        const state=check.ok?await check.json():null;
        if(state?.authenticated){await refreshAccount();dialog.close();dialog.remove();if(typeof authSuccess==='function')await authSuccess(state);return;}
      }
      await renderAuth(response,dialog);
    }
    catch{dialog.querySelector('[data-account-auth-content]').textContent=modalCopy[8];}
  }
  async function renderAuth(response,dialog){
    const page=parse(await response.text()),content=page.querySelector('[data-account-auth-content]');
    if(!content)throw new Error('No account content');
    // Scripts parsed with DOMParser are inert when moved into the dialog.
    content.querySelectorAll('script').forEach(script=>script.remove());
    dialog.querySelector('[data-account-auth-content]').replaceChildren(...Array.from(content.childNodes));
    const form=dialog.querySelector('form');if(form)form.action=response.url;
    dialog.dataset.authUrl=response.url;
    if(dialog.querySelector('[data-residence]')){
      for(const name of ['account-residence.js','floating-select.js']){
        const selector=`script[src*="/assets/${name}"]`;
        const resource=page.querySelector(selector);
        if(!resource||document.querySelector(selector))continue;
        const script=document.createElement('script');
        script.src=resource.getAttribute('src');
        document.head.append(script);
      }
    }
    startResendCountdowns(dialog);
    document.dispatchEvent(new CustomEvent('el:auth-render'));
    dialog.querySelector('input:not([type=hidden]),button[type=submit],a')?.focus();
  }
  document.addEventListener('click',event=>{
    const close=event.target.closest('[data-account-auth-dialog] .account-login-close');
    if(close){event.preventDefault();closeAuth(close.closest('dialog'));return;}
    const link=event.target.closest('a[href]');if(!link||event.defaultPrevented||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey||link.target==='_blank')return;
    const url=new URL(link.href,location.href);if(url.origin!==location.origin||!authPath(url)||url.searchParams.has('token'))return;
    event.preventDefault();const nested=!!link.closest('[data-account-auth-dialog]');showLogin(nested?authTrigger:link,nested?authSuccess:null,url.href,nested?authCancel:null);
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
        if(state?.authenticated){await refreshAccount();dialog.close();dialog.remove();if(typeof authSuccess==='function')await authSuccess(state);else {document.dispatchEvent(new CustomEvent('el:authenticated',{detail:state}));if(document.body.classList.contains('portal-account-page')||new URL(response.url).pathname.includes('/suscripciones/'))location.assign(response.url);}return;}
      }
      await renderAuth(response,dialog);
    }catch{let status=form.querySelector('[role=alert]');if(!status){status=document.createElement('p');status.setAttribute('role','alert');form.append(status);}status.textContent=modalCopy[8];}
    finally{delete form.dataset.busy;if(submit)submit.disabled=false;}
  });
  const initialDialog=document.querySelector('[data-account-auth-dialog]');
  if(initialDialog){initialDialog.dataset.authUrl=location.href;initialDialog.removeAttribute('open');initialDialog.showModal();initialDialog.addEventListener('cancel',event=>{event.preventDefault();closeAuth(initialDialog);});}
  document.addEventListener('el:request-login',event=>showLogin(event.detail?.trigger,event.detail?.onSuccess,event.detail?.url||'/negocios/acceso/',event.detail?.onCancel));
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
    const name=document.createElement('span');name.className='account-name';name.textContent=account.name;
    name.setAttribute('aria-label',copy[3]+': '+account.name);
    const form = document.createElement('form');
    form.className='account-logout-form';
    form.method = 'post';
    form.action = '/negocios/salir/';
    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = 'csrf';
    csrf.value = account.csrf;
    const logout = document.createElement('button');
    logout.className='account-logout-button';
    logout.type = 'submit';
    logout.textContent = copy[1];
    form.append(csrf, logout);
    const myAccount=document.createElement('a');myAccount.href='/negocios/portal/';myAccount.textContent=copy[2];
    const actions=[name,myAccount,form];
    if(account.administrator){const admin=document.createElement('a');admin.href='/admin/index.php';admin.textContent=locale==='en'?'Administration':locale==='pt-br'?'Administração':'Administración';actions.push(admin);}
    bar.replaceChildren(...actions);
  } catch { signIn(); }
  }
  await refreshAccount();
  window.addEventListener('pageshow', e => {if(e.persisted)refreshAccount();});
})();
