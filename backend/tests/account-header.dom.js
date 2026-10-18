'use strict';
// Structural DOM regression without network, real accounts or browser dependencies.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync(require('node:path').join(__dirname,'../public/assets/account-entry.js'),'utf8');
class Element {
  constructor(tag){this.tagName=tag;this.children=[];this.attributes={};this.className='';this.classList={add:name=>this.className+=' '+name};}
  append(...nodes){for(const node of nodes){node.remove();node.parent=this;this.children.push(node);}}
  prepend(node){node.remove();node.parent=this;this.children.unshift(node);}
  before(node){const parent=this.parent;node.remove();node.parent=parent;parent.children.splice(parent.children.indexOf(this),0,node);}
  remove(){if(this.parent){this.parent.children=this.parent.children.filter(x=>x!==this);this.parent=null;}}
  setAttribute(key,value){this.attributes[key]=value;}
  hasAttribute(key){return Object.hasOwn(this.attributes,key);}
  addEventListener(){}
  replaceChildren(...nodes){this.children.forEach(node=>node.parent=null);this.children=[];this.append(...nodes);}
  querySelector(selector){return this.querySelectorAll(selector)[0]||null;}
  querySelectorAll(selector){const match=node=>selector[0]==='.'?node.className.split(' ').includes(selector.slice(1)):node.tagName===selector;return this.children.flatMap(node=>[...(match(node)?[node]:[]),...node.querySelectorAll(selector)]);}
}
async function scenario({withLanguages,authenticated,existing=false,legacy=false,administrator=true}){
 const body=new Element('body'),header=new Element('header'),languages=new Element('span');languages.className='language-switch';
 if(withLanguages){for(const code of ['ES','EN','PT']){const a=new Element('a');a.textContent=code;languages.append(a);}header.append(languages);}
 body.append(header);
 if(existing){const strip=new Element('div');strip.className='portal-account-strip portal-account-fallback';if(legacy)strip.setAttribute('data-admin-legacy','');const inner=new Element('div');inner.className='portal-account-strip-inner';const link=new Element('a');link.textContent=legacy?'Administrador':'Iniciar sesión';inner.append(link);strip.append(inner);body.prepend(strip);}
 const context={document:{documentElement:{lang:'es'},querySelector:s=>body.querySelector(s),createElement:t=>new Element(t)},window:{addEventListener(){}},fetch:async()=>({ok:true,json:async()=>({enabled:true,authenticated,name:'Persona',csrf:'stub',administrator})})};
 await vm.runInNewContext(source,context);
 const strip=body.querySelector('.portal-account-strip');assert.ok(strip);
 if(withLanguages){assert.equal(languages.children[0],strip);assert.equal(body.children.length,1,'no additional header row');assert.equal(languages.children.slice(1).map(x=>x.textContent).join(','),'ES,EN,PT');}
 const bar=strip.querySelector('.account-entry');
 if(authenticated){assert.equal(bar.children[0].tagName,'details');assert.equal(bar.children[1].textContent,'Mi cuenta');assert.equal(bar.children[0].querySelector('button').textContent,'Cerrar sesión');assert.equal(bar.children[0].querySelector('a').textContent,'Contacto y ayuda');assert.equal(bar.children.length,administrator?3:2);if(administrator){assert.equal(bar.children[2].textContent,'Administración');assert.equal(bar.children[2].href,'/admin/index.php');}}
 else assert.equal(bar.children[0].textContent,legacy?'Administrador':'Iniciar sesión');
 await vm.runInNewContext(source,context);assert.equal(body.querySelectorAll('.portal-account-strip').length,1,'idempotent entry');
}
(async()=>{for(const authenticated of [false,true])for(const existing of [false,true])await scenario({withLanguages:true,authenticated,existing});await scenario({withLanguages:false,authenticated:true,existing:true});await scenario({withLanguages:true,authenticated:true,administrator:false});await scenario({withLanguages:false,authenticated:false,existing:true,legacy:true});console.log('PASS: entry before ES/EN/PT, no extra public row, single mount, account dropdown and PHP/legacy fallbacks.');})().catch(error=>{console.error(error);process.exitCode=1;});
