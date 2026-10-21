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
  const frame = document.createElement('iframe'); frame.title = labels.title;
  toolbar.append(title,close); dialog.append(toolbar,loading,frame); document.body.append(dialog);
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
  frame.addEventListener('load',() => { loading.hidden = true; });
  document.addEventListener('click', event => {
    const link = event.target.closest('a[data-subscription-example]');
    if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    const url = new URL(link.href);
    if (url.origin !== location.origin || url.pathname !== '/business/demo.php') return;
    event.preventDefault(); trigger = link; loading.hidden = false;
    const view = url.searchParams.get('view'); dialog.dataset.view = view;
    url.searchParams.set('embedded','1'); frame.src = url.href;
    document.documentElement.classList.add('subscription-example-open'); dialog.showModal(); close.focus();
  });
})();
