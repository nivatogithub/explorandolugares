// Isolated DOM contract + independent decoding of the actual rendered QR pixels.
// No browser, API, production request or persistent dataset is used.
'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),path=require('node:path');
const decode=require('./vendor/jsQR-1.4.0.js'),assets=path.join(__dirname,'../public/assets');
const pixels=new Uint8ClampedArray(2048*2048*4),events={},timers=[],downloads=[],blobs=[];
function element(){return {textContent:'',hidden:false,disabled:false,style:{},listeners:{},addEventListener(n,fn){this.listeners[n]=fn;},setAttribute(k,v){this[k]=v;},removeAttribute(k){delete this[k];},focus(){this.focused=true;},append(){},remove(){}};}
const dialog=element();dialog.open=false;dialog.showModal=()=>dialog.open=true;dialog.close=()=>{dialog.open=false;dialog.listeners.close();};
const canvas=element();canvas.width=canvas.height=2048;
const context={fillStyle:'',fillRect(x,y,w,h){const value=this.fillStyle==='#ffffff'?255:0;for(let row=y;row<y+h;row++)for(let col=x;col<x+w;col++){const i=(row*2048+col)*4;pixels[i]=pixels[i+1]=pixels[i+2]=value;pixels[i+3]=255;}}};
canvas.getContext=()=>context;canvas.toBlob=(fn,type)=>{assert.equal(type,'image/png');blobs.push(fn);};
const name=element(),link=element(),status=element(),download=element(),body=element();body.style.overflow='auto';
const nodes={'business-qr-dialog':dialog,'business-qr-canvas':canvas,'business-qr-name':name,'business-qr-url':link,'business-qr-status':status,'business-qr-download':download};
const document={getElementById:id=>nodes[id],addEventListener(n,fn){events[n]=fn;},body,createElement(tag){assert.equal(tag,'a');const a=element();a.click=()=>downloads.push({href:a.href,filename:a.download});return a;}};
let created=0,revoked=0;
class LocalURL extends URL{static createObjectURL(blob){assert.equal(blob.type,'image/png');created++;return 'blob:local-qr';}static revokeObjectURL(url){assert.equal(url,'blob:local-qr');revoked++;}}
const runtime={document,URL:LocalURL,setTimeout(fn){timers.push(fn);}};vm.createContext(runtime);
vm.runInContext(fs.readFileSync(path.join(assets,'vendor/qrcodegen-v1.8.0.js'),'utf8'),runtime);
vm.runInContext(fs.readFileSync(path.join(assets,'business-qr.js'),'utf8'),runtime);
const url='https://explorandolugares.com/negocios/f00d0000-0000-4000-8000-000000000012/';
const trigger=element();trigger.dataset={businessQrUrl:url,businessQrName:'Página de prueba <sin HTML>',businessQrFilename:'qr-pagina-de-prueba.png'};
const click=()=>events.click({target:{closest:()=>trigger}});
click();assert(dialog.open);assert.equal(body.style.overflow,'hidden');assert.equal(link.href,url);assert.equal(name.textContent,trigger.dataset.businessQrName);assert(!download.disabled);
assert.equal(canvas.width,2048);assert.equal(canvas.height,2048);
const decoded=decode(pixels,2048,2048);assert(decoded,'The rendered QR is unreadable');assert.equal(decoded.data,url);
// Quiet zone stays white and every pixel stays fully opaque.
for(let x=0;x<2048;x++)assert.equal(pixels[x*4],255);
for(let i=3;i<pixels.length;i+=4)assert.equal(pixels[i],255);
download.listeners.click();assert(download.disabled);blobs.shift()({type:'image/png'});
assert.deepEqual(downloads,[{href:'blob:local-qr',filename:'qr-pagina-de-prueba.png'}]);assert(!download.disabled);assert.equal(created,1);timers.shift()();assert.equal(revoked,1);
// A failed export permits a retry; closing during async export cannot save a stale QR.
download.listeners.click();blobs.shift()(null);assert(!download.disabled);assert(status.textContent.includes('No se pudo'));
download.listeners.click();dialog.close();blobs.shift()({type:'image/png'});assert.equal(downloads.length,1);assert.equal(body.style.overflow,'auto');assert(trigger.focused);
// External URLs cannot be disguised as a business QR; no old QR or link is exposed.
trigger.dataset.businessQrUrl='https://evil.example/negocios/f00d0000-0000-4000-8000-000000000012/';click();assert(dialog.open);assert(canvas.hidden);assert(download.disabled);assert.equal(link.href,undefined);dialog.close();
// Missing generator produces a visible message and restores scrolling on close.
vm.runInContext('qrcodegen=undefined',runtime);trigger.dataset.businessQrUrl=url;click();assert(download.disabled);assert(status.textContent.includes('No se pudo'));dialog.close();assert.equal(body.style.overflow,'auto');
console.log('PASS: independently decoded URL; opaque 2048px QR, quiet zone, modal/focus/scroll, PNG download, cleanup, failed export, stale callback and offline errors.');
