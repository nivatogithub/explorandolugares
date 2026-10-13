const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const script=fs.readFileSync('ensayos/v1/assets/telefono.js','utf8');
for(const lang of ['es','en','pt-BR']) {
 const type={value:'celular',addEventListener(event,fn){this[event]=fn}};
 const number={value:'',validity:{patternMismatch:true},setCustomValidity(value){this.message=value},addEventListener(event,fn){this[event]=fn}};
 const prefix={},help={};
 vm.runInNewContext(script,{document:{documentElement:{lang},getElementById(id){return {'business-registration':{elements:{phone_type:type,phone_number:number},querySelectorAll(){return [type]}},'phone-prefix':prefix,'phone-help':help}[id]}}});
 assert.equal(prefix.textContent,'+57');assert.equal(number.maxLength,10);
 assert(new RegExp('^'+number.pattern+'$').test('3001234567'));assert(!new RegExp('^'+number.pattern+'$').test('1234567890'));
 number.invalid();assert(number.message);number.input();assert.equal(number.message,'');
 type.value='whatsapp';type.change();assert.equal(prefix.textContent,'+57');assert.equal(number.maxLength,10);assert(new RegExp('^'+number.pattern+'$').test('3001234567'));
 type.value='fijo';type.change();assert.equal(prefix.textContent,'+57 604');assert.equal(number.maxLength,7);
 assert(new RegExp('^'+number.pattern+'$').test('1234567'));assert(!new RegExp('^'+number.pattern+'$').test('6041234567'));
}
console.log('OK: radios celular, WhatsApp y fijo, prefijos automáticos, longitudes y mensajes ES/EN/PT-BR.');
