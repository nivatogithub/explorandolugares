'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
class Element {
  constructor(){this.dataset={};this.hidden=false;this.children=[];this.listeners={};this.nodeType=1;}
  append(node){this.children.push(node);}
  querySelector(){return this.children.find(node=>node.dataset.businessTextCounter)||null;}
  querySelectorAll(){return [];}
  addEventListener(name,callback){this.listeners[name]=callback;}
  setAttribute(){}
}
class Input extends Element{}
class Textarea extends Element{}
const label=new Element(),field=new Textarea();
field.value='Descripción';field.type='textarea';field.maxLength=100;field.closest=()=>label;
field.dataset.textCounter='';
const excluded=['display_name','business_name','business_nit','business_rnt','phone','email','address_text'].map(name=>{
  const input=new Input(),holder=new Element();input.name=name;input.value='Prueba';input.type='text';input.maxLength=180;input.closest=()=>holder;return {input,holder};
});
let mutation;
const document={readyState:'complete',body:new Element(),querySelectorAll:()=>[field,...excluded.map(item=>item.input)],createElement:()=>new Element()};
vm.runInNewContext(fs.readFileSync(__dirname+'/../public/assets/business-text-counters.js','utf8'),{
  document,HTMLElement:Element,HTMLInputElement:Input,HTMLTextAreaElement:Textarea,
  MutationObserver:class{constructor(callback){mutation=callback;}observe(){}},
});
const counter=label.children[0];assert.equal(counter.textContent,'11 / 100 caracteres');
field.maxLength=600;mutation([{type:'attributes',target:field}]);
assert.equal(counter.textContent,'11 / 600 caracteres','Update denominator when plan changes');
field.value='á'.repeat(500);field.listeners.input();
field.maxLength=300;mutation([{type:'attributes',target:field}]);
assert.equal(counter.textContent,'500 / 300 caracteres · Superas el límite; resume el texto o elige otro plan.');
assert.equal(field.value.length,500,'Counter must not truncate existing content');
assert.equal(label.children.length,1,'Do not duplicate counters after plan changes');
field.value='á'.repeat(300);field.listeners.input();
assert.match(counter.textContent,/300 \/ 300 caracteres · Límite alcanzado/);
for(const {input,holder} of excluded){mutation([{type:'attributes',target:input}]);assert.equal(holder.children.length,0,'No counter for '+input.name);assert.equal(input.maxLength,180,'Keep validation limit for '+input.name);}
const reason=new Textarea(),reasonLabel=new Element();reason.value='ñ'.repeat(200);reason.type='textarea';reason.maxLength=200;reason.closest=()=>reasonLabel;
reason.dataset.textCounter='';reason.dataset.autogrow='';reason.dataset.textCounterLimitMessage=' · Superas el límite; resume el motivo.';
reason.style={};reason.scrollHeight=220;reason.offsetHeight=164;reason.clientHeight=160;reason.getClientRects=()=>[{}];
mutation([{type:'attributes',target:reason}]);assert.match(reasonLabel.children[0].textContent,/200 \/ 200 caracteres · Límite alcanzado/);
assert.equal(reason.style.height,'224px','Textarea grows to show all text including borders');
reason.value+='ñ';reason.listeners.input();assert.equal(reasonLabel.children[0].textContent,'201 / 200 caracteres · Superas el límite; resume el motivo.');
console.log('PASS: opted-in description counters, dynamic 100/600/300, limit hints, no counters on identity/contact/address fields and unchanged validation.');
