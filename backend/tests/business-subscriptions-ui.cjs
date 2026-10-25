const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
let listener;
const button={disabled:false,textContent:'Enviar comprobante'};
const form={addEventListener:(event,callback)=>{assert.equal(event,'submit');listener=callback;},querySelector:()=>button};
const code=fs.readFileSync(__dirname+'/../public/assets/business-subscriptions.js','utf8');
vm.runInNewContext(code,{document:{querySelectorAll:()=>[form]}});
assert.equal(button.disabled,false);listener();assert.equal(button.disabled,true);assert.equal(button.textContent,'Enviando comprobante…');
assert.ok(!/\.remove\(|textContent\.includes|startsWith/.test(code),'No legacy DOM patching or section removal');
console.log('PASS: payment submit progress only, no client pricing or legacy dashboard rewrites.');
