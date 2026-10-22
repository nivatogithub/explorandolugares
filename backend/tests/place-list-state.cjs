'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const script=fs.readFileSync('ensayos/v1/assets/destinos.js','utf8');
const storage=new Map();
function load(href){
 const cards=Array.from({length:18},(_,i)=>({dataset:{name:'Lugar '+i,region:'Antioquia'},hidden:false}));
 const nodes={};for(const id of ['destination-search','destination-region','destination-results','destination-previous','destination-next','destination-pagination','destination-page-number','destination-clear','destination-controls'])nodes[id]={value:'',options:[{value:''},{value:'Antioquia'}],addEventListener(type,fn){this[type]=fn;},focus(){},dispatchEvent(){}};
 const grid={querySelectorAll:()=>cards,scrollIntoView(){}};nodes['destination-grid']=grid;
 const location={href,pathname:'/lugares/'};
 vm.runInNewContext(script,{document:{documentElement:{lang:'es'},getElementById:id=>nodes[id]},location,URL,sessionStorage:{getItem:key=>storage.get(key),setItem:(key,value)=>storage.set(key,value)},history:{replaceState(_a,_b,url){location.href=url.href;}}});
 return {nodes,cards,location};
}
const first=load('http://localhost/lugares/');first.nodes['destination-next'].click();assert.match(first.location.href,/pagina=2/);assert.equal(first.cards[0].hidden,true);assert.equal(first.cards[9].hidden,false);
const back=load(first.location.href);assert.equal(back.cards[9].hidden,false);assert.equal(back.cards[0].hidden,true);
const breadcrumb=load('http://localhost/lugares/');assert.equal(breadcrumb.cards[9].hidden,false);
breadcrumb.nodes['destination-search'].value='Lugar 1';breadcrumb.nodes['destination-search'].input();assert.equal(breadcrumb.nodes['destination-previous'].disabled,true);assert.equal(new URL(breadcrumb.location.href).searchParams.get('pagina'),null);
console.log('OK: second list page survives browser return and list breadcrumb; filtering resets pagination.');
