(()=>{
 const form=document.querySelector('[data-template-editor]');if(!form)return;
 const frame=document.querySelector('[data-template-preview]'),subject=document.querySelector('[data-template-subject]'),status=document.querySelector('[data-template-preview-status]');let timer,sequence=0;
 async function refresh(){const current=++sequence;const data=new FormData(form);data.set('action','preview_template');status.textContent='Actualizando vista previa…';
  try{const response=await fetch('correos.php?tab=templates',{method:'POST',body:data,credentials:'same-origin'});if(!response.ok)throw Error();const result=await response.json();if(current!==sequence)return;frame.srcdoc=result.html;subject.textContent=result.subject;status.textContent='Vista previa actualizada con datos de ejemplo.';}catch{if(current===sequence)status.textContent='No se pudo actualizar la vista previa. Revisa el contenido o vuelve a intentarlo.';}}
 form.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(refresh,350);});refresh();
})();
