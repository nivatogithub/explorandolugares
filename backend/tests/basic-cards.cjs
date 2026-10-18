// Unit simulation only, no browser automation or production requests.
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
(async()=>{
 const requests=[],listeners={},clock=new Map();let now=0,next=0,observer;
 const id='aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',card={dataset:{businessId:id},getClientRects:()=>[{}]};
 const node=()=>({append(){},prepend(){},remove(){},setAttribute(){},addEventListener(){}});
 const document={documentElement:{lang:'es'},referrer:'',visibilityState:'visible',head:node(),body:node(),createElement:node,createElementNS:node,querySelector:s=>s==='footer'?node():null,querySelectorAll:()=>[card],addEventListener:(name,fn)=>(listeners[name]??=[]).push(fn)};
 class Observer{constructor(callback){this.callback=callback;observer=this;}observe(){}unobserve(){}trigger(ratio){this.callback([{target:card,isIntersecting:ratio>0,intersectionRatio:ratio}]);}}
 const window={IntersectionObserver:Observer,dispatchEvent(){},addEventListener(){}};
 const context={window,document,navigator:{doNotTrack:'0'},localStorage:{getItem:()=>JSON.stringify({allowed:false,version:'measurement-v1',expires:Date.now()+999999}),setItem(){}},location:new URL('https://test.invalid/destinos/jerico/'),URL,Event:class{constructor(type){this.type=type}},crypto:{randomUUID:()=> 'not-used'},IntersectionObserver:Observer,setTimeout:(fn,delay)=>{const key=++next;clock.set(key,{fn,due:now+delay});return key;},clearTimeout:key=>clock.delete(key),fetch:(url,options={})=>{requests.push({url,options});return Promise.resolve({ok:true,json:()=>Promise.resolve(url==='/api/medicion.php'?{enabled:true}:{accepted:true})});}};
 const tick=async(ms)=>{now+=ms;for(const [key,t] of [...clock])if(t.due<=now){clock.delete(key);t.fn();}await Promise.resolve();};
 vm.runInNewContext(fs.readFileSync(__dirname+'/../public/assets/cobertura/medicion.js','utf8'),context);
 await window.elBasicPageCount;await Promise.resolve();assert.ok(observer);
 observer.trigger(.5);await tick(500);document.visibilityState='hidden';listeners.visibilitychange.forEach(f=>f());await tick(2000);
 assert.equal(requests.filter(r=>r.options.body?.includes('card_impression')).length,0,'hidden tab must not qualify');
 document.visibilityState='visible';listeners.visibilitychange.forEach(f=>f());await tick(999);assert.equal(requests.filter(r=>r.options.body?.includes('card_impression')).length,0);await tick(1);
 observer.trigger(.8);await tick(2000);assert.equal(requests.filter(r=>r.options.body?.includes('card_impression')).length,1,'one visible impression per document');
 const link={href:'https://test.invalid/negocios/'+id+'/',getAttribute:()=>'/negocios/'+id+'/',closest:selector=>selector==='.directory-card[data-business-id]'?card:null};
 const event={isTrusted:true,target:{closest:()=>link}};listeners.click.forEach(f=>f(event));listeners.click.forEach(f=>f(event));await Promise.resolve();
 assert.equal(requests.filter(r=>r.options.body?.includes('profile_click')).length,1,'one profile click per card/document');
 const basic=requests.filter(r=>r.url==='/api/actividad.php');assert.equal(basic.length,3);for(const request of basic){assert.equal(request.options.credentials,'omit');assert.equal(request.options.referrerPolicy,'no-referrer');const body=JSON.parse(request.options.body);assert.ok(!('visitor_id' in body)&&!('event_id' in body));}
 const beforeRepeat=requests.length;vm.runInNewContext(fs.readFileSync(__dirname+'/../public/assets/cobertura/medicion.js','utf8'),context);assert.equal(requests.length,beforeRepeat,'direct and shared loaders must not duplicate measurement');
 const beforePreview=requests.length;const previewContext={...context,window:{IntersectionObserver:Observer},document:{...document,querySelector:selector=>selector==='.destination-preview-banner'?node():null}};vm.runInNewContext(fs.readFileSync(__dirname+'/../public/assets/cobertura/medicion.js','utf8'),previewContext);assert.equal(requests.length,beforePreview,'private previews never track, even without cookies');
 console.log('Basic cards: OK — visible 50%/1s, hidden-tab pause, once per document, profile click dedup and no credentials/referrer/visitor IDs.');
})().catch(error=>{console.error(error);process.exitCode=1;});
