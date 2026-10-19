const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const data=JSON.parse(fs.readFileSync('ensayos/v1/assets/contact-options.json','utf8')),script=fs.readFileSync('ensayos/v1/assets/opciones-contacto.js','utf8');
class Node{constructor(fragment=false){this.fragment=fragment;this.children=[];this.dataset={};this.listeners={};this.value='';}append(...items){for(const n of items)this.children.push(...(n.fragment?n.children:[n]));}replaceChildren(...items){this.children=[];this.append(...items);}addEventListener(name,fn){this.listeners[name]=fn;}dispatchEvent(event){this.listeners[event.type]?.(event);}get options(){return this.children;}get selectedOptions(){return this.children.filter(n=>n.value===this.value);}}
async function load(lang,requested='',fail=false){const category=new Node(),municipality=new Node(),contact_department=new Node();category.value='experiencias';
 const form={elements:{category,municipality,contact_department}};
 vm.runInNewContext(script,{URL,Event:class{constructor(type){this.type=type;}},document:{URL:'https://example.invalid/negocios-locales/interes/?municipio='+encodeURIComponent(requested),currentScript:{src:'https://example.invalid/assets/opciones-contacto.js'},documentElement:{lang},getElementById:()=>form,createElement:()=>new Node(),createDocumentFragment:()=>new Node(true)},fetch:async()=>({ok:!fail,json:async()=>data})});await new Promise(r=>setImmediate(r));return form.elements;}
(async()=>{
 assert.equal(data.nationalMunicipalities.length,1122);assert.equal(Object.keys(data.departments).length,33);
 for(const lang of ['es','en','pt-BR'])for(const [name,code,dept,prefix] of [['Bogotá','11001','11','601'],['Cartagena','13001','13','605'],['Santa Marta','47001','47','605'],['Medellín','05001','05','604'],['Jericó','05368','05','604'],['Mocoa','86001','86','608'],['Zipaquirá','25899','25','601']]){
  const e=await load(lang,name);assert.equal(e.municipality.value,code);assert.equal(e.contact_department.value,dept);assert.equal(e.municipality.selectedOptions[0].dataset.landlinePrefix,prefix);assert.equal(e.category.value,'experiencias');assert.equal(e.contact_department.options.length,34);
 }
 const e=await load('es','inventado');assert.equal(e.contact_department.value,'');e.contact_department.value='86';e.contact_department.listeners.change();assert(e.municipality.options.some(n=>n.value==='86001'));assert(!e.municipality.options.some(n=>n.value==='11001'));
 const ambiguous=await load('es','Bolívar');assert.equal(ambiguous.contact_department.value,'','Duplicate names must not pick a random department');
 const failed=await load('es','Bogotá',true);assert.equal(failed.category.children.length,0,'Static fallback not destroyed by failed fetch');
 console.log('OK: 1122 national units, 33 departments/district, ES/EN/PT prefill, ambiguous names, server prefixes and failure fallback.');
})().catch(e=>{console.error(e);process.exitCode=1});
