'use strict';
const fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const code=fs.readFileSync(path.join(__dirname,'../public/assets/tarjetas-negocios-compactas.js'),'utf8');
const cards=['free','featured','page','page_premium'].map(plan=>({dataset:{directoryLayout:'editorial-v2'},querySelector(){throw Error('The legacy script queried an approved '+plan+' card');},querySelectorAll(){throw Error('The legacy script changed an approved '+plan+' card');}}));
vm.runInNewContext(code,{document:{addEventListener(){},documentElement:{lang:'es'},readyState:'complete',querySelectorAll:()=>cards},window:{addEventListener(_event,handler){handler();}}});
if(cards.some(card=>card.dataset.iconLayout))throw Error('An approved card was marked as legacy');
console.log('PASS: legacy compaction leaves all four approved editorial card variants untouched.');
