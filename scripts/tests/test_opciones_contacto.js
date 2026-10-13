const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const data=JSON.parse(fs.readFileSync('ensayos/v1/assets/contact-options.json','utf8'));
const script=fs.readFileSync('ensayos/v1/assets/opciones-contacto.js','utf8');
function node(){return {children:[],append(child){this.children.push(child)},replaceChildren(fragment){this.children=fragment.children}};}
async function check(lang,fail=false,requested='',initial='Jericó'){
 const category={...node(),value:'experiencias'},municipality={...node(),value:initial,options:data.municipalities.map(value=>({value}))};
 const form={elements:{category,municipality}};
 vm.runInNewContext(script,{URL,document:{URL:'http://127.0.0.1:8000/v1/negocios-locales/index.html?municipio='+encodeURIComponent(requested),currentScript:{src:'http://127.0.0.1:8000/v1/assets/opciones-contacto.js'},documentElement:{lang},getElementById(){return form},createElement:node,createDocumentFragment:node},fetch:async ()=>({ok:!fail,json:async()=>data})});
 await new Promise(resolve=>setImmediate(resolve));
 assert.equal(category.value,'experiencias');assert.equal(municipality.value,initial || (data.municipalities.includes(requested) ? requested : ''));
 if(fail){assert.equal(category.children.length,0);return;}
 assert.equal(municipality.children.length,22);
 assert(municipality.children.some(item=>item.value==='Jericó'));
 assert(!municipality.children.some(item=>item.value==='Envigado'));
 assert(!('municipalityCatalog' in data));
 assert.equal(category.children.slice(1).reduce((sum,group)=>sum+group.children.length,0),11);
 assert.equal(category.children[1].label,lang==='en'?'Service type':'Tipo de servicio');
}
(async()=>{await check('es');await check('en');await check('es',true);await check('es',false,'Medellín','');await check('en',false,'Jardín','');await check('es',true,'Guatapé','');await check('es',false,'Envigado','');await check('es',false,'Medellín','Jericó');console.log('OK: carga JSON ES/EN, 21 municipios del piloto, 11 categorías, conserva selección y respaldo ante fallo.');})().catch(e=>{console.error(e);process.exitCode=1});
