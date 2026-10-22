(async () => {
  'use strict';
  if(document.querySelector('.account-entry'))return;
  const header=document.querySelector('header');
  const existing=document.querySelector('.portal-account-strip');
  if(!header&&!existing)return;
  if(!document.querySelector('link[data-account-page-style]')){const style=document.createElement('link');style.rel='stylesheet';style.href='/assets/account-page.css';style.dataset.accountPageStyle='';document.head.append(style);}
  const locale = document.documentElement.lang.startsWith('pt') ? 'pt-br' : document.documentElement.lang.startsWith('en') ? 'en' : 'es';
  const copy = {es: ['Iniciar sesión', 'Cerrar sesión', 'Mi cuenta', 'Tu cuenta'], en: ['Log in', 'Sign out', 'My account', 'Your account'], 'pt-br': ['Iniciar sessão', 'Sair', 'Minha conta', 'Sua conta']}[locale];
  const languages=header?.querySelector('.language-switch');
  const strip=existing||document.createElement('span');strip.className='portal-account-strip';strip.setAttribute('aria-label',copy[3]);
  const bar=existing?.querySelector('.portal-account-strip-inner')||document.createElement('span');bar.className='portal-account-strip-inner account-entry';
  if(!existing)strip.append(bar);
  if(languages){strip.classList.add('portal-account-inline');languages.prepend(strip);languages.classList.add('language-switch-with-account');}
  else if(!existing){strip.classList.add('portal-account-fallback');header.before(strip);}

  const modalCopy = {es: ['Correo electrónico','Contraseña','Entrar','Olvidé mi contraseña','¿No tienes cuenta?','Crear cuenta','Cerrar','Preparando acceso…','No se pudo iniciar sesión. Inténtalo de nuevo.'],en: ['Email','Password','Sign in','Forgot password?','No account yet?','Create account','Close','Preparing sign-in…','Unable to sign in. Please try again.'],'pt-br': ['E-mail','Senha','Entrar','Esqueceu sua senha?','Ainda não tem conta?','Cadastre-se','Fechar','Preparando acesso…','Não foi possível entrar. Tente novamente.']}[locale];
  const authTranslations={
    'Inicia sesión':['Sign in','Iniciar sessão'], 'Iniciar sesión':['Sign in','Iniciar sessão'],
    'Tu cuenta personal':['Your personal account','Sua conta pessoal'], 'Tu cuenta':['Your account','Sua conta'],
    'Correo electrónico':['Email','E-mail'], 'Contraseña':['Password','Senha'], 'Entrar a mi cuenta':['Sign in','Entrar na minha conta'],
    '¿Aún no tienes cuenta?':['No account yet?','Ainda não tem conta?'], 'Crear cuenta personal':['Create personal account','Criar conta pessoal'],
    'Olvidé mi contraseña':['Forgot password?','Esqueci minha senha'], 'Recuperar contraseña':['Recover password','Recuperar senha'],
    'Solicitar enlace de recuperación':['Send recovery link','Enviar link de recuperação'], 'Volver a iniciar sesión':['Back to sign in','Voltar ao início de sessão'],
    'Contactar al equipo':['Contact the team','Falar com a equipe'], 'Crea tu cuenta personal':['Create your personal account','Crie sua conta pessoal'],
    'Una cuenta personal para viajar y administrar tus negocios. Verificaremos tu correo antes de activar el acceso.':['One personal account for travel and managing your businesses. Verify your email to activate access.','Uma conta pessoal para viajar e administrar seus negócios. Confirme seu e-mail para ativar o acesso.'],
    'Tu nombre':['Your name','Seu nome'], '¿Dónde vives?':['Where do you live?','Onde você mora?'],
    'Tu residencia como persona. No es la ubicación de tus negocios y no se publica en el directorio.':['Your personal residence, separate from your business location. It is not published in the directory.','Sua residência pessoal, separada da localização dos seus negócios. Não é publicada no diretório.'],
    'País':['Country','País'], 'Selecciona tu país':['Select your country','Selecione seu país'], 'Confirma la contraseña':['Confirm password','Confirme a senha'],
    'Al menos 12 caracteres, con letras y números.':['At least 12 characters, including letters and numbers.','Pelo menos 12 caracteres, com letras e números.'],
    'Acepto el tratamiento de mis datos para crear y gestionar mi cuenta según la':['I agree to the processing of my data to create and manage my account under the','Aceito o tratamento dos meus dados para criar e gerenciar minha conta conforme a'],
    'política de privacidad':['privacy policy','política de privacidade'], 'Crear cuenta':['Create account','Criar conta'],
    '¿Ya tienes cuenta?':['Already have an account?','Já tem conta?'], 'Nueva contraseña':['New password','Nova senha'],
    'Guardar contraseña':['Save password','Salvar senha'], 'Contraseña actualizada':['Password updated','Senha atualizada'],
    'Correo verificado':['Email verified','E-mail confirmado'], 'Verifica tu correo electrónico':['Verify your email','Confirme seu e-mail'],
    'Solicitar nuevo enlace':['Request another link','Solicitar novo link'], '← Negocios locales':['← Local businesses','← Negócios locais']
  };
  function translateAuth(dialog){if(locale==='es')return;const walker=document.createTreeWalker(dialog,NodeFilter.SHOW_TEXT);let node;while((node=walker.nextNode())){const text=node.textContent.trim(),translation=authTranslations[text];if(translation)node.textContent=node.textContent.replace(text,translation[locale==='en'?0:1]);}dialog.querySelector('.account-login-close')?.setAttribute('aria-label',modalCopy[6]);}
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
    translateAuth(dialog);const form=dialog.querySelector('form');if(form)form.action=response.url;
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
  if(initialDialog){translateAuth(initialDialog);initialDialog.dataset.authUrl=location.href;initialDialog.removeAttribute('open');initialDialog.showModal();initialDialog.addEventListener('cancel',()=>location.assign('/'));}
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
