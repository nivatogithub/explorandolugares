(() => {
  'use strict';
  if (document.documentElement.dataset.subscriptionExamples) return;
  document.documentElement.dataset.subscriptionExamples = 'ready';
  const language = document.documentElement.lang;
  const en = language.startsWith('en'), pt = language.startsWith('pt');
  const labels = en ? {title:'Subscription example',close:'Close example',loading:'Loading example…'} : pt ? {title:'Exemplo de assinatura',close:'Fechar exemplo',loading:'Carregando exemplo…'} : {title:'Ejemplo de suscripción',close:'Cerrar ejemplo',loading:'Cargando ejemplo…'};
  const dialog = document.createElement('dialog');
  dialog.className = 'subscription-example-dialog';
  dialog.setAttribute('aria-labelledby','subscription-example-title');
  dialog.dataset.subscriptionExampleDialog = '';
  const toolbar = document.createElement('div'); toolbar.className = 'subscription-example-toolbar';
  const title = document.createElement('h2'); title.id = 'subscription-example-title'; title.textContent = labels.title;
  const close = document.createElement('button'); close.type = 'button'; close.className = 'pill light'; close.textContent = labels.close + ' ×';
  const loading = document.createElement('p'); loading.className = 'subscription-example-loading'; loading.textContent = labels.loading; loading.setAttribute('role','status');
  const views = document.createElement('nav'); views.className = 'subscription-example-views'; views.hidden = true;
  views.setAttribute('aria-label',en?'Preview presentation':pt?'Apresentação do exemplo':'Presentación del ejemplo');
  const cardButton = document.createElement('button'); cardButton.type = 'button'; cardButton.textContent = en?'Directory card':pt?'Cartão no diretório':'Tarjeta en el directorio';
  const pageButton = document.createElement('button'); pageButton.type = 'button'; pageButton.textContent = en?'Business page':pt?'Página do negócio':'Página del negocio';
  views.append(cardButton,pageButton);
  const frame = document.createElement('iframe'); frame.title = labels.title;
  const syncView = presentation => { cardButton.setAttribute('aria-pressed',String(presentation==='card')); pageButton.setAttribute('aria-pressed',String(presentation==='page')); };
  const openView = presentation => {
    const url = new URL('/business/demo.php',location.origin);
    url.searchParams.set('view',dialog.dataset.view); url.searchParams.set('embedded','1'); url.searchParams.set('presentation',presentation);
    loading.hidden = false; syncView(presentation); frame.src = url.href;
  };
  cardButton.addEventListener('click',()=>openView('card')); pageButton.addEventListener('click',()=>openView('page'));
  toolbar.append(title,close); dialog.append(toolbar,views,loading,frame); document.body.append(dialog);
  let trigger = null;
  close.addEventListener('click',() => dialog.close());
  dialog.addEventListener('click', event => {
    const rect = dialog.getBoundingClientRect();
    if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
  });
  dialog.addEventListener('close',() => {
    frame.removeAttribute('src'); document.documentElement.classList.remove('subscription-example-open');
    trigger?.focus({preventScroll:true});
  });
  frame.addEventListener('load',() => { loading.hidden = true; try { const url = new URL(frame.contentWindow.location.href); if(url.pathname==='/business/demo.php')syncView(url.searchParams.get('presentation')||'page'); } catch {} });
  document.addEventListener('click', event => {
    const link = event.target.closest('a[data-subscription-example]');
    if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    const url = new URL(link.href);
    if (url.origin !== location.origin || url.pathname !== '/business/demo.php') return;
    event.preventDefault(); trigger = link; loading.hidden = false;
    const view = url.searchParams.get('view'); dialog.dataset.view = view;
    const hasPage = ['page','page_premium'].includes(view); views.hidden = !hasPage;
    url.searchParams.set('embedded','1'); if(hasPage){url.searchParams.set('presentation','card');syncView('card');} frame.src = url.href;
    document.documentElement.classList.add('subscription-example-open'); dialog.showModal(); close.focus();
  });
})();
