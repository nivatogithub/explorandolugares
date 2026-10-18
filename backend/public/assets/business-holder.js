(()=>{'use strict';for(const fieldset of document.querySelectorAll('[data-holder-identity]')){
 const kind=fieldset.querySelector('[name=holder_kind]'),type=fieldset.querySelector('[name=holder_document_type]'),number=fieldset.querySelector('[name=holder_number]'),foreignKind=fieldset.querySelector('[name=holder_foreign_kind]');
 const isNit=()=>['legal_entity','natural_rut'].includes(kind.value)||(kind.value==='foreign'&&type.value==='NIT');
 function show(label,visible){label.hidden=!visible;for(const control of label.querySelectorAll('input,select')){control.disabled=!visible;control.required=visible;control.dispatchEvent(new Event('floating-select:refresh'));}}
 function update(){const selected=!!kind.value,foreign=kind.value==='foreign',company=kind.value==='legal_entity'||(foreign&&foreignKind.value==='company');
 for(const label of fieldset.querySelectorAll('[data-holder-detail]'))show(label,selected);
 for(const label of fieldset.querySelectorAll('[data-holder-foreign]'))show(label,foreign);
 show(type.closest('label'),selected&&!['legal_entity','natural_rut'].includes(kind.value));
 const allowed=!foreign?['CC']:foreignKind.value==='company'?['FOREIGN_TAX','NIT']:['CE','PPT','PASSPORT','FOREIGN_TAX','NIT'];
 for(const option of type.options)option.hidden=!!option.value&&!allowed.includes(option.value);
 if(type.value&&!allowed.includes(type.value))type.value='';type.dispatchEvent(new Event('floating-select:refresh'));
 fieldset.querySelector('[data-holder-name-label]').textContent=company?'Razón social':'Nombre completo del titular';
 fieldset.querySelector('#holder-number-help').hidden=!selected;
 const nit=isNit();number.placeholder=nit?'Ejemplo: 800197268-4':'';number.inputMode=['CC','CE','PPT'].includes(type.value)?'numeric':'text';
 fieldset.querySelector('[data-holder-number-label]').textContent=nit?'NIT con dígito de verificación':'Número de documento';
 fieldset.querySelector('#holder-number-help').textContent=nit?'Copia el NIT y su dígito de verificación del RUT.':type.value==='CC'?'Escribe la cédula del titular. No se registrará como NIT.':type.value==='CE'?'Escribe la cédula de extranjería. Su número no se convierte en NIT.':'Copia el número del documento seleccionado.';validate();}
 function validate(){number.setCustomValidity('');if(!number.value)return;if(isNit()){const match=number.value.replace(/[.\s]/g,'').match(/^([0-9]{5,15})-([0-9])$/);if(!match||!/[1-9]/.test(match[1])){number.setCustomValidity('Escribe el NIT con guion y dígito de verificación.');return;}const weights=[71,67,59,53,47,43,41,37,29,23,19,17,13,7,3],digits=match[1].padStart(15,'0');const rest=[...digits].reduce((sum,digit,i)=>sum+Number(digit)*weights[i],0)%11;if(Number(match[2])!==(rest<2?rest:11-rest))number.setCustomValidity('El dígito de verificación no coincide. Cópialo de tu RUT.');}
 else if(['CC','CE','PPT'].includes(type.value)&&(!/^[0-9]{4,15}$/.test(number.value)||!/[1-9]/.test(number.value)))number.setCustomValidity('Escribe entre 4 y 15 dígitos.');
 else if(!['CC','CE','PPT'].includes(type.value)&&(!/^[A-Za-z0-9][A-Za-z0-9 .-]{3,29}$/.test(number.value)||!/[A-Za-z1-9]/.test(number.value)))number.setCustomValidity('Escribe entre 4 y 30 caracteres válidos del documento.');}
 kind.addEventListener('change',update);foreignKind.addEventListener('change',update);type.addEventListener('change',update);number.addEventListener('input',validate);fieldset.closest('form').addEventListener('submit',event=>{validate();if(!number.reportValidity())event.preventDefault();});update();
}})();
