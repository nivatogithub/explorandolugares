'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),crypto=require('node:crypto');
const source=fs.readFileSync(__dirname+'/../public/assets/cobertura/medicion.js','utf8');
class Element{constructor(tag){this.tagName=tag;this.children=[];this.listeners={};this.dataset={};this.parent=null;this.textContent='';}append(...children){for(const c of children){this.children.push(c);c.parent=this;}}prepend(c){this.children.unshift(c);c.parent=this;}setAttribute(k,v){this[k]=v;}addEventListener(k,fn){this.listeners[k]=fn;}remove(){if(this.parent)this.parent.children=this.parent.children.filter(c=>c!==this);}querySelectorAll(){return [];}querySelector(){return null;}}
const settle=()=>new Promise(resolve=>setImmediate(resolve));
async function run(values,prepared=false){const calls=[],body=new Element('body'),head=new Element('head'),footer=new Element('footer');body.append(footer);let initialButton=null;if(prepared){initialButton=new Element('button');initialButton.className='el-measurement-preferences';initialButton.textContent='Privacidad y estadísticas';footer.append(initialButton);const style=new Element('link');style.href='/assets/cobertura/medicion.css';head.append(style);}const events={},window={addEventListener:(name,fn)=>{events[name]=fn;},dispatchEvent:()=>{}};
 const localStorage={getItem:k=>values.get(k)??null,setItem:(k,v)=>values.set(k,v)};
 const document={documentElement:{lang:'es'},head,body,querySelector:q=>q==='footer'?footer:q==='footer .el-measurement-preferences'?initialButton:(prepared&&q.startsWith('link[href*=')?head.children[0]:null),querySelectorAll:()=>[],createElement:t=>new Element(t),createElementNS:(_,t)=>new Element(t),addEventListener:()=>{},referrer:''};
 const context={window,document,localStorage,navigator:{},location:{pathname:'/',href:'https://example.test/',hostname:'example.test'},URL,Date,JSON,crypto,WeakSet,Map,Set,Event:class{},setTimeout,clearTimeout,fetch:(url,options={})=>{calls.push({url,options});return Promise.resolve({ok:true,json:()=>Promise.resolve(url==='/api/actividad.php'?{accepted:true}:{enabled:true})});}};
 vm.runInNewContext(source,context);await settle();await settle();return {calls,window,body,head,footer,events,initialButton};}
function all(el){return [el,...el.children.flatMap(all)];}
(async()=>{
 const key='el_measurement_excluded_v1',choice='el_measurement_choice_v1',saved=JSON.stringify({allowed:true,version:'measurement-v1',expires:Date.now()+86400000});
 let r=await run(new Map([[key,'1'],[choice,saved]]));r.window.elMeasure('contact_click',{business_id:'test'});await settle();assert.equal(r.calls.filter(c=>c.url==='/api/actividad.php'&&c.options.method==='POST').length,0,'Excluded browser must not submit basic or optional events');
 const storage=new Map([[choice,saved]]);r=await run(storage);assert(r.calls.some(c=>c.url==='/api/actividad.php'&&c.url==='/api/actividad.php'&&c.options.method==='POST'));assert(!r.calls.some(c=>c.url==='/api/medicion.php'&&JSON.parse(c.options.body||'{}').event),'No optional events or analytic identifiers');assert(!all(r.body).some(x=>x.className==='el-measurement-consent'),'No automatic consent interruption');
 const prefs=r.footer.children.find(x=>x.className==='el-measurement-preferences');prefs.listeners.click();const box=all(r.body).find(x=>x.type==='checkbox');box.checked=true;box.listeners.change();await settle();assert.equal(storage.get(key),'1');const after=r.calls.length;r.window.elMeasure('page_view');await settle();assert.equal(r.calls.length,after,'No events after opting out');
 const reload=await run(storage);assert.equal(reload.calls.filter(c=>c.url==='/api/actividad.php'&&c.options.method==='POST').length,0,'Exclusion survives reload and overrides previous consent');
 const readyExcluded=await run(new Map([[key,'1']]),true);
 assert.equal(readyExcluded.initialButton.textContent,'Privacidad y estadísticas','Exclusion state does not resize the footer label');
 const ready=await run(new Map(),true);
 assert.equal(ready.footer.children.length,1,'Do not append a duplicate or replace the prepared button');
 assert.equal(ready.footer.children[0],ready.initialButton,'Reuse the exact initial footer node');
 assert.equal(ready.head.children.length,1,'Do not request a late stylesheet when it is present in the head');
 assert.equal(ready.initialButton.textContent,'Privacidad y estadísticas','Stable visible label after initialization');
 assert(!all(ready.body).some(x=>x.className==='el-measurement-consent'),'No automatic cookie or consent popup');
 ready.initialButton.listeners.click();assert(all(ready.body).some(x=>x.className==='el-measurement-consent'),'Preferences open only on explicit click');
 assert(ready.calls.filter(c=>c.url==='/api/actividad.php').every(c=>c.options.credentials==='omit'),'Basic measurement never sends cookies');
 console.log('OK: browser exclusion blocks basic/optional events, applies immediately, persists and overrides consent.');
})().catch(e=>{console.error(e);process.exitCode=1;});
