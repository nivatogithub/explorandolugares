'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),crypto=require('node:crypto');
const source=fs.readFileSync(__dirname+'/../public/assets/cobertura/medicion.js','utf8');
class Element{constructor(tag){this.tagName=tag;this.children=[];this.listeners={};this.dataset={};this.parent=null;this.textContent='';}append(...children){for(const c of children){this.children.push(c);c.parent=this;}}prepend(c){this.children.unshift(c);c.parent=this;}setAttribute(k,v){this[k]=v;}addEventListener(k,fn){this.listeners[k]=fn;}remove(){if(this.parent)this.parent.children=this.parent.children.filter(c=>c!==this);}querySelectorAll(){return [];}querySelector(){return null;}}
const settle=()=>new Promise(resolve=>setImmediate(resolve));
async function run(values){const calls=[],body=new Element('body'),head=new Element('head'),footer=new Element('footer');body.append(footer);const events={},window={addEventListener:(name,fn)=>{events[name]=fn;},dispatchEvent:()=>{}};
 const localStorage={getItem:k=>values.get(k)??null,setItem:(k,v)=>values.set(k,v)};
 const document={documentElement:{lang:'es'},head,body,querySelector:q=>q==='footer'?footer:null,querySelectorAll:()=>[],createElement:t=>new Element(t),createElementNS:(_,t)=>new Element(t),addEventListener:()=>{},referrer:''};
 const context={window,document,localStorage,navigator:{},location:{pathname:'/',href:'https://example.test/',hostname:'example.test'},URL,Date,JSON,crypto,WeakSet,Map,Set,Event:class{},setTimeout,clearTimeout,fetch:(url,options={})=>{calls.push({url,options});return Promise.resolve({ok:true,json:()=>Promise.resolve(url==='/api/actividad.php'?{accepted:true}:{enabled:true})});}};
 vm.runInNewContext(source,context);await settle();await settle();return {calls,window,body,footer,events};}
function all(el){return [el,...el.children.flatMap(all)];}
(async()=>{
 const key='el_measurement_excluded_v1',choice='el_measurement_choice_v1',saved=JSON.stringify({allowed:true,version:'measurement-v1',expires:Date.now()+86400000});
 let r=await run(new Map([[key,'1'],[choice,saved]]));r.window.elMeasure('contact_click',{business_id:'test'});await settle();assert.equal(r.calls.filter(c=>c.options.method==='POST').length,0,'Excluded browser must not submit basic or optional events');
 const storage=new Map([[choice,saved]]);r=await run(storage);assert(r.calls.some(c=>c.url==='/api/actividad.php'&&c.options.method==='POST'));assert(r.calls.some(c=>c.url==='/api/medicion.php'&&JSON.parse(c.options.body||'{}').event==='page_view'));
 const prefs=r.footer.children.find(x=>x.className==='el-measurement-preferences');prefs.listeners.click();const box=all(r.body).find(x=>x.type==='checkbox');box.checked=true;box.listeners.change();await settle();assert.equal(storage.get(key),'1');const after=r.calls.length;r.window.elMeasure('page_view');await settle();assert.equal(r.calls.length,after,'No events after opting out');
 const reload=await run(storage);assert.equal(reload.calls.filter(c=>c.options.method==='POST').length,0,'Exclusion survives reload and overrides previous consent');
 console.log('OK: browser exclusion blocks basic/optional events, applies immediately, persists and overrides consent.');
})().catch(e=>{console.error(e);process.exitCode=1;});
