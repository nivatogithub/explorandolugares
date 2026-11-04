const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
class Element {constructor(tag){this.tag=tag;this.children=[];this.attrs={};this.style={};this.events={};}append(...v){this.children.push(...v);}replaceChildren(){this.children=[];}setAttribute(k,v){this.attrs[k]=String(v);}addEventListener(k,v){this.events[k]=v;}focus(){this.events.focus?.();}}
const source=fs.readFileSync(__dirname+'/../public/assets/cobertura/coverage-meta.js','utf8');
const fn=source.slice(source.indexOf('  function trend('),source.indexOf('  function refreshAudienceFilters'));
function nodes(e){return [e,...e.children.filter(x=>x instanceof Element).flatMap(nodes)];}
for(const peak of [0,1,100,1000,12500]){
 const host=new Element('div');const ctx={document:{createElementNS:(_,t)=>new Element(t)},$:()=>host,el:(t,text,c)=>{const e=new Element(t);e.textContent=text;e.attrs.class=c;return e;},number:String};vm.createContext(ctx);vm.runInContext(fn,ctx);
 ctx.trend({status:'enabled',trend:[{day:'2026-11-03',views:0},{day:'2026-11-04',views:peak}]});
 const all=nodes(host),scale=all.filter(e=>e.attrs.class==='trend-scale'),targets=all.filter(e=>e.attrs.class==='trend-date-target');assert(scale.length<=5);assert.equal(targets.length,2);
 targets[1].events.click();assert.equal(all.find(e=>e.attrs.class==='trend-readout').textContent,`2026-11-04: ${peak} páginas consultadas`);
 targets[1].events.keydown({key:'ArrowLeft',preventDefault(){}});assert.equal(targets[0].attrs['aria-pressed'],'true');
 assert.equal(all.filter(e=>e.attrs.class==='trend-grid').length,2);
 if(peak===100)assert.deepEqual(scale.map(e=>e.textContent),['0','25','50','75','100']);
 if(peak===1000)assert.deepEqual(scale.map(e=>e.textContent),['0','250','500','750','1000']);
}
console.log('OK: bounded scale, daily verticals, click, zero counts and keyboard');
