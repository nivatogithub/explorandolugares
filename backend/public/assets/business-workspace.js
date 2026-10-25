(() => {
  'use strict';
  const dialog=document.querySelector('.business-preview-dialog');
  if(!dialog)return;
  const frame=dialog.querySelector('iframe'),title=dialog.querySelector('h2'),close=dialog.querySelector('[data-close-business-preview]');
  const changes=document.querySelector('#business-changes-dialog'),notice=document.querySelector('#business-notice-dialog');
  let trigger=null;
  document.addEventListener('click',event=>{
    const correction=event.target.closest('[data-owner-changes]');
    if(correction&&changes){
      const source=correction.closest('form'),form=changes.querySelector('form');form.reset();
      for(const name of ['business_id','version_id','payload_hash'])form.elements.namedItem(name).value=source.elements.namedItem(name).value;
      form.elements.namedItem('note').dispatchEvent(new Event('input',{bubbles:true}));
      changes.querySelector('[data-changes-business]').textContent=correction.dataset.businessName;
      trigger=correction;changes.showModal();document.documentElement.classList.add('business-preview-open');form.elements.namedItem('note').focus();return;
    }
    const information=event.target.closest('[data-business-notice]');
    if(information&&notice){
      notice.querySelector('[data-business-notice-text]').textContent=information.dataset.businessNotice;
      trigger=information;notice.showModal();document.documentElement.classList.add('business-preview-open');notice.querySelector('button').focus();return;
    }
    const link=event.target.closest('a[data-business-preview]');
    if(!link||event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
    const url=new URL(link.href,location.href);if(url.origin!==location.origin)return;
    event.preventDefault();trigger=link;title.textContent=link.dataset.previewTitle||'Vista previa de tu negocio';frame.title=title.textContent;
    frame.src=url.href;dialog.showModal();document.documentElement.classList.add('business-preview-open');close.focus();
  });
  close.addEventListener('click',()=>dialog.close());
  dialog.addEventListener('close',()=>frame.removeAttribute('src'));
  for(const modal of [dialog,changes,notice].filter(Boolean)){
    modal.querySelectorAll('[data-close-dialog]').forEach(button=>button.addEventListener('click',()=>modal.close()));
    modal.addEventListener('close',()=>{document.documentElement.classList.remove('business-preview-open');trigger?.focus();});
  }
})();
