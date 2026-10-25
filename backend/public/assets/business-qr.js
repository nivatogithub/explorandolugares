/* QR generation and PNG export stay in this browser. No network/API calls. */
(()=>{
 'use strict';
 const dialog=document.getElementById('business-qr-dialog');if(!dialog)return;
 const canvas=document.getElementById('business-qr-canvas'),name=document.getElementById('business-qr-name'),link=document.getElementById('business-qr-url'),status=document.getElementById('business-qr-status'),download=document.getElementById('business-qr-download');
 let opener=null,filename='',previousOverflow='',generation=0;
 const validUrl=value=>{const u=new URL(value);if(u.origin!=='https://explorandolugares.com'||!/^\/negocios\/[a-f0-9-]{16,64}\/$/i.test(u.pathname)||u.search||u.hash||u.username||u.password)throw Error('URL');return u.href;};
 document.addEventListener('click',event=>{
  const trigger=event.target.closest('[data-business-qr-url]');if(!trigger)return;
  if(dialog.open)return;
  opener=trigger;previousOverflow=document.body.style.overflow;generation++;download.disabled=true;canvas.hidden=false;
  name.textContent=trigger.dataset.businessQrName||'Tu negocio';link.textContent='';link.removeAttribute('href');
  try{
   const url=validUrl(trigger.dataset.businessQrUrl),qr=qrcodegen.QrCode.encodeText(url,qrcodegen.QrCode.Ecc.QUARTILE),context=canvas.getContext('2d');
   if(!context)throw Error('Canvas');
   const scale=Math.floor(2048/(qr.size+8)),offset=Math.floor((2048-qr.size*scale)/2);
   canvas.width=canvas.height=2048;context.fillStyle='#ffffff';context.fillRect(0,0,2048,2048);context.fillStyle='#000000';
   for(let y=0;y<qr.size;y++)for(let x=0;x<qr.size;x++)if(qr.getModule(x,y))context.fillRect(offset+x*scale,offset+y*scale,scale,scale);
   name.textContent=trigger.dataset.businessQrName;link.textContent=url;link.href=url;canvas.setAttribute('aria-label','Código QR de '+name.textContent);
   filename=/^qr-[a-z0-9-]+\.png$/.test(trigger.dataset.businessQrFilename||'')?trigger.dataset.businessQrFilename:'qr-negocio.png';
   download.disabled=false;status.textContent='PNG de 2048 × 2048 píxeles. Conserva el margen blanco al imprimir.';

  }catch{status.textContent='No se pudo generar el QR. Recarga la página e inténtalo de nuevo.';canvas.hidden=true;download.disabled=true;}
  dialog.showModal();document.body.style.overflow='hidden';
 });
 dialog.addEventListener('close',()=>{generation++;document.body.style.overflow=previousOverflow;canvas.hidden=false;opener?.focus({preventScroll:true});});
 download.addEventListener('click',()=>{
  const current=generation,savedFilename=filename;download.disabled=true;
  canvas.toBlob(blob=>{
   if(current!==generation)return;
   download.disabled=false;if(!blob){status.textContent='No se pudo preparar el PNG. Inténtalo de nuevo.';return;}
   const url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download=savedFilename;document.body.append(a);a.click();a.remove();
   setTimeout(()=>URL.revokeObjectURL(url),60000);status.textContent='PNG preparado para descargar. Puedes imprimirlo o compartirlo.';
  },'image/png');
 });
})();
