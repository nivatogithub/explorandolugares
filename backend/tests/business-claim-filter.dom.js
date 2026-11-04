'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
class Element {
  constructor(tag){this.tagName=tag;this.children=[];this.listeners={};this.dataset={};this.hidden=false;this.disabled=false;}
  addEventListener(event,listener){this.listeners[event]=listener;}
  setAttribute(key,value){this[key]=value;}
  append(...nodes){this.children.push(...nodes);}
  replaceChildren(...nodes){this.children=nodes;}
  querySelector(){return null;}
}
const form=new Element('form'),input=new Element('input'),list=new Element('div'),count=new Element('small'),empty=new Element('p'),submit=new Element('button');
form.dataset={verified:'Verified information',unverified:'Public information to be confirmed',count:'({count} found, maximum 500)',updateError:'Could not update the search.'};
form.querySelectorAll=()=>[];form.querySelector=()=>input;
const ids={'business-claim-results':list,'business-claim-result-count':count,'business-claim-empty':empty,'business-claim-submit':submit};
let fail=false;
const context={
  document:{querySelector:()=>form,getElementById:id=>ids[id],createElement:tag=>new Element(tag)},
  window:{location:{pathname:'/business-claim.php'},clearTimeout(){},setTimeout(fn){fn();}},
  FormData:class {constructor(){return [['destino','1'],['categoria','hotel']];}},
  URLSearchParams,AbortController,
  fetch:async()=>{if(fail)throw new Error('offline');return {ok:true,json:async()=>({results:[{id:7,display_name:'<Hotel>',information_status:'VERIFICADO',address_text:'<Street>'}]})};}
};
vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../public/assets/business-claim-filter.js'),'utf8'),context);
input.listeners.input();
setImmediate(()=>{
  try {
    assert.equal(count.textContent,'(1 found, maximum 500)');
    assert.equal(list.children[0].children[1].children[0].textContent,'<Hotel>');
    assert.equal(list.children[0].children[1].children[1].textContent,'Verified information · <Street>');
    assert.equal(empty.hidden,true);assert.equal(submit.disabled,false);
    fail=true;input.listeners.input();
    setImmediate(()=>{try {assert.equal(empty.textContent,'Could not update the search.');assert.equal(empty.hidden,false);console.log('PASS: live claim results use localized labels and insert data as text.');}catch(e){console.error(e);process.exitCode=1;}});
  } catch(e){console.error(e);process.exitCode=1;}
});
