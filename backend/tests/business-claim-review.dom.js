'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
class Element{
 constructor(tag){this.tagName=tag;this.children=[];this.listeners={};this.value='';this.hidden=false;this.disabled=false;this.dataset={};}
 append(...nodes){this.children.push(...nodes);}
 after(node){this.notice=node;}
 replaceChildren(...nodes){this.children=nodes;}
 setAttribute(key,value){this[key]=value;}
 addEventListener(event,listener){this.listeners[event]=listener;}
 dispatchEvent(event){this.listeners[event.type]?.(event);}
 querySelectorAll(){return this.children.flatMap(node=>node.tagName==='input'?[node]:node.querySelectorAll());}
 focus(){}
}
const ids=['business-registration-form','registration-destination','registration-category','registration-search','registration-results','registration-not-found','registration-application-type','registration-business-id','registration-new-name-wrap','registration-new-name','registration-submit','registration-relationship','registration-authority-wrap','registration-authority-document'];
const nodes=Object.fromEntries(ids.map(id=>['#'+id,new Element('div')]));
const form=nodes['#business-registration-form'];form.dataset.searchUrl='/business/register.php';form.querySelectorAll=()=>nodes['#registration-results'].querySelectorAll();
let message='<script>alert("no")</script> Necesita revisión, sin acceso automático.';
const context={document:{querySelector:id=>nodes[id]||null,createElement:tag=>new Element(tag)},Event:function(type){this.type=type;},setTimeout:callback=>callback(),clearTimeout(){},fetch:async()=>({json:async()=>({results:[{id:8,display_name:'Negocio ocupado',can_request:true,requires_review:true,review_message:message}]})}),Option:function(){}};
vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../public/assets/business-registration.js'),'utf8'),context);
nodes['#registration-destination'].value='1';nodes['#registration-category'].value='test';nodes['#registration-search'].value='Negocio';nodes['#registration-search'].listeners.input();
setImmediate(()=>{
 try{
  const results=nodes['#registration-results'],radio=results.querySelectorAll()[0];
  assert.equal(radio.disabled,false,'occupied business remains requestable');
  assert.equal(results.children[1].children[1].children[1].textContent,message,'message written as text, never markup');
  radio.listeners.change();assert.equal(nodes['#registration-business-id'].value,'8');assert.equal(nodes['#registration-application-type'].value,'existing_business');
  assert.equal(results.notice.textContent,message);assert.equal(results.notice.hidden,false);
  nodes['#registration-not-found'].listeners.click();assert.equal(results.notice.hidden,true,'clear old claim warning when creating a new business');
  assert.equal(nodes['#registration-application-type'].value,'new_business');
  console.log('PASS: disputed business selectable, warning shown as text before and after selection, warning clears for new business.');
 }catch(error){console.error(error);process.exitCode=1;}
});
