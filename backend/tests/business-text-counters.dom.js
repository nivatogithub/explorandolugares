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
field.value='Descripción';field.type='textarea';field.maxLength=300;field.closest=()=>label;
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
const counter=label.children[0];assert.equal(counter.textContent,'11 / 300 caracteres');
field.maxLength=1200;mutation([{type:'attributes',target:field}]);
assert.equal(counter.textContent,'11 / 1200 caracteres','Update denominator when plan changes');
field.value='á'.repeat(800);field.listeners.input();
field.maxLength=450;mutation([{type:'attributes',target:field}]);
assert.equal(counter.textContent,'800 / 450 caracteres · Superas el límite; resume el texto o elige otro plan.');
assert.equal(field.value.length,800,'Counter must not truncate existing content');
assert.equal(label.children.length,1,'Do not duplicate counters after plan changes');
field.value='á'.repeat(450);field.listeners.input();
assert.match(counter.textContent,/450 \/ 450 caracteres · Límite alcanzado/);
for(const {input,holder} of excluded){mutation([{type:'attributes',target:input}]);assert.equal(holder.children.length,0,'No counter for '+input.name);assert.equal(input.maxLength,180,'Keep validation limit for '+input.name);}
console.log('PASS: opted-in description counters, dynamic 300/1200/450, limit hints, no counters on identity/contact/address fields and unchanged validation.');
