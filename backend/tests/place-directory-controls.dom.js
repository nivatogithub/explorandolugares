'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(__dirname + '/../public/assets/place-directory.js', 'utf8');
function fixture(href = 'https://example.test/lugares/?pagina=2', stored = null) {
  const events = new Map();
  const target = extra => Object.assign({addEventListener(name, fn) { events.set(this.id + ':' + name, fn); }, focus() { doc.activeElement = this; }}, extra);
  const fields = Object.fromEntries(['q','department','region','heritage'].map(name => [name, target({id:name,name,value:'',disabled:false})]));
  fields.heritage.checked = false;
  fields.q.selectionStart = 2; fields.q.setSelectionRange = (start,end) => { fields.q.selectionStart = start; };
  const clearFilters = target({id:'clearFilters'});
  const status = {setAttribute(){},textContent:''};
  const form = target({id:'form',action:'https://example.test/lugares/',dataset:{updating:'Actualizando'},classList:{add(){}},elements:Object.assign(Object.values(fields),{namedItem:name=>fields[name]}),querySelector:()=>clearFilters,reset(){}});
  const doc = target({id:'document',activeElement:fields.q,querySelector:selector=>selector==='[data-place-filters]'?form:status});
  const win = target({id:'window'}); const pending = new Map(); let count=0; const destinations=[];
  const location = {href,pathname:'/lugares/',assign:url=>destinations.push(url),reload(){}};
  const sessionStorage = {setItem(_,value){stored=value;},getItem(){return stored;},removeItem(){stored=null;}};
  class FormData { *[Symbol.iterator]() { for(const field of Object.values(fields)) if(!field.disabled && (!(field.type==='checkbox'||field.name==='heritage')||field.checked)) yield [field.name,field.name==='heritage'?'1':field.value]; } }
  vm.runInNewContext(source,{document:doc,window:win,location,sessionStorage,URL,FormData,setTimeout:fn=>{pending.set(++count,fn);return count;},clearTimeout:id=>pending.delete(id)});
  return {fields,status,destinations,doc,saved:()=>stored,emit:(id,name,event={})=>events.get(id+':'+name)(event),flush:()=>{const jobs=[...pending.values()];pending.clear();jobs.forEach(fn=>fn());},pending};
}
let f=fixture(); f.fields.department.value='Antioquia';f.fields.region.value='Caribe';f.fields.heritage.checked=true;
f.emit('form','change',{target:f.fields.region});
let query=new URL(f.destinations[0]).searchParams;
assert.equal(query.has('department'),false);assert.equal(query.get('region'),'Caribe');assert.equal(query.get('heritage'),'1');assert.equal(query.has('pagina'),false);
assert.equal(f.fields.department.value,'');assert.equal(f.status.textContent,'Actualizando');
f=fixture();f.fields.region.value='Caribe';f.fields.department.value='Cesar';f.emit('form','change',{target:f.fields.department});assert.equal(new URL(f.destinations[0]).searchParams.get('region'),'Caribe');
f=fixture('https://example.test/lugares/');f.fields.q.value='Jerico';f.emit('q','input');assert.equal(f.destinations.length,0);
f.fields.q.value='Jericó';f.emit('q','input');assert.equal(f.pending.size,1);f.flush();assert.equal(new URL(f.destinations[0]).searchParams.get('q'),'Jericó');
f=fixture('https://example.test/lugares/');f.emit('q','compositionstart');f.fields.q.value='Jardín';f.emit('q','input');f.flush();assert.equal(f.destinations.length,0);f.emit('q','compositionend');f.flush();assert.equal(new URL(f.destinations[0]).searchParams.get('q'),'Jardín');
f=fixture('https://example.test/lugares/?q=Jerico');f.fields.q.value='';f.emit('q','input');f.flush();assert.equal(f.destinations[0],'https://example.test/lugares/');
f=fixture('https://example.test/lugares/');f.emit('form','submit',{preventDefault(){}});assert.equal(f.destinations.length,0);
f=fixture('https://example.test/lugares/',JSON.stringify({focus:'q',cursor:2}));assert.equal(f.doc.activeElement,f.fields.q);assert.equal(f.fields.q.selectionStart,2);
f=fixture();f.emit('q','input');f.emit('clearFilters','click');assert.equal(f.pending.size,0);
console.log('OK: region resets dependent department, department preserves region, first page, debounce, IME, clear-all, same URL and focus restoration.');
