'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync(__dirname+'/../public/assets/saved-place.js','utf8');
let submit, fetchResult,requests=0,blocked;
class Form{}
const attrs={'aria-pressed':'false'},svg={fill:'none',setAttribute(k,v){this[k]=v;}},button={disabled:false,setAttribute(k,v){attrs[k]=v;},removeAttribute(k){delete attrs[k];},querySelector(){return svg;}};
const action={value:'save_place'},feedback={textContent:''},authLink={hidden:true};
const form=new Form();Object.assign(form,{action:{value:'save_place'},getAttribute(name){return name==='action'?'/business/saved-place.php':null;},dataset:{authenticated:'1',saveError:'Error',authRequired:'Registered users only'},matches(){return true;},elements:{namedItem(){return action;}},querySelector(s){return s==='.saved-place-action'?button:s==='[data-saved-place-feedback]'?feedback:authLink;}});
const sandbox={HTMLFormElement:Form,FormData:class{},AbortController,setTimeout,clearTimeout,document:{addEventListener(e,fn){if(e==='submit')submit=fn;}},fetch:async(url)=>{assert.equal(url,'/business/saved-place.php');requests++;if(blocked)await blocked;return fetchResult;}};
vm.runInNewContext(source,sandbox);
const event=()=>({target:form,preventDefault(){}});
(async()=>{
 fetchResult={ok:true,status:200,json:async()=>({ok:true,saved:true,label:'Guardado'})};await submit(event());assert.equal(attrs['aria-pressed'],'true');assert.equal(svg.fill,'currentColor');assert.equal(action.value,'remove_place');
 fetchResult={ok:true,status:200,json:async()=>({ok:true,saved:false,label:'Guardar'})};await submit(event());assert.equal(attrs['aria-pressed'],'false');assert.equal(svg.fill,'none');assert.equal(action.value,'save_place');
 fetchResult={ok:false,status:503,json:async()=>({ok:false})};await submit(event());assert.equal(attrs['aria-pressed'],'false');assert.equal(feedback.textContent,'Error');assert.equal(button.disabled,false);
 fetchResult={ok:false,status:401,json:async()=>({error:'authentication_required'})};await submit(event());assert.equal(authLink.hidden,false);assert.equal(feedback.textContent,'Registered users only');
 let release;blocked=new Promise(r=>release=r);const pending=submit(event());const before=requests;await submit(event());assert.equal(requests,before);release();await pending;
 assert(!/location\.(?:assign|replace|reload)|scrollTo|innerHTML|insertAdjacentHTML|createElement/.test(source));
 console.log('OK async bookmark: save/remove authoritative state, failure preserves state, expired session invitation, duplicate submission blocked, no navigation/scroll/HTML injection');
})().catch(e=>{console.error(e);process.exitCode=1;});
