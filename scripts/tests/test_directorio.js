const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const script=fs.readFileSync('ensayos/v1/assets/catalogo.js','utf8');
const options=JSON.parse(fs.readFileSync('ensayos/v1/assets/contact-options.json','utf8'));
class Element {
 constructor(tag){this.tag=tag;this.children=[];this.dataset={};this.attributes={};this.events={};}
 append(...children){this.children.push(...children);}
 replaceChildren(...children){this.children=children;}
 setAttribute(key,value){this.attributes[key]=value;}
 addEventListener(key,fn){this.events[key]=fn;}
 focus(){this.focused=true;}
}
const walk=(node,test)=>[...(test(node)?[node]:[]),...node.children.flatMap(child=>walk(child,test))];
const base={destino:'jerico',registroConfirmado:true,informacionVerificada:true,estado:'publicado',slug:'lugar-prueba',nombre:'Prueba <b>literal</b>',tipoServicio:'hospedaje',descripcion:'Texto de prueba',descripcionEn:'Test description',contactoUrl:'https://example.invalid',imagen:'assets/jerico.webp'};
async function run(lang,records,fail=false,reviews=false){
 const container=new Element('div');container.dataset.destino='jerico';
 vm.runInNewContext(script,{URL,document:{currentScript:{src:'http://localhost:8000/assets/catalogo.js'},documentElement:{lang},getElementById(){return container},createElement:tag=>new Element(tag)},fetch:async url=>({ok:!fail,json:async()=>String(url).includes('contact-options')?options:{publicaciones:records,resenasHabilitadas:reviews}})});
 await new Promise(resolve=>setImmediate(resolve));return container;
}
(async()=>{
 for(const lang of ['es','en']){
  const container=await run(lang,[base,{...base,slug:'comida-prueba',tipoServicio:'alimentacion'},{...base,slug:'borrador',estado:'borrador'},{...base,slug:'no-verificado',informacionVerificada:false},{...base,slug:'otro-destino',destino:'otro'},{...base,slug:'desconocido',tipoServicio:'desconocido'}]);
  const tabs=walk(container,node=>node.attributes.role==='tab');assert.equal(tabs.length,2);
  assert.equal(walk(container,node=>node.className==='directory-card').length,2);
  const photo=walk(container,node=>node.tag==='img')[0];assert.equal(photo.src,'http://localhost:8000/assets/jerico.webp');
  const links=walk(container,node=>node.tag==='a');assert(links.some(link=>link.href.includes(lang==='en'?'/en/destinos/jerico/lugares/':'/destinos/jerico/lugares/')));
  const food=tabs.find(tab=>tab.dataset.type==='descansar-comer');food.events.click();assert.equal(walk(container,node=>node.className==='directory-card').length,2);
  const service=walk(container,node=>node.id==='directory-service')[0];service.value='alimentacion';service.events.change();assert.equal(walk(container,node=>node.className==='directory-card').length,1);
  assert.equal(food.attributes['aria-selected'],'true');assert.equal(food.tabIndex,0);
  food.events.keydown({key:'Home',preventDefault(){}});assert.equal(tabs[0].attributes['aria-selected'],'true');assert(tabs[0].focused);
  assert.equal(walk(container,node=>node.className==='directory-card').length,2);
  assert.equal(walk(container,node=>node.id==='directory-rating').length,0);
  const empty=await run(lang,[]);assert.equal(walk(empty,node=>node.attributes.role==='tab').length,1);assert.equal(walk(empty,node=>node.className==='directory-card').length,0);
 }
 const find=(container,id)=>walk(container,node=>node.id===id)[0];
 const searchContainer=await run('es',[{...base,nombre:'Café de prueba',descripcion:'Desayunos artesanales',tipoServicio:'alimentacion'}, {...base,slug:'hotel',nombre:'Hotel prueba'}]);
 const search=find(searchContainer,'directory-search');search.value='cafe desayunos';search.events.input();assert.equal(walk(searchContainer,node=>node.className==='directory-card').length,1);
 search.value='sin-coincidencias';search.events.input();assert.equal(walk(searchContainer,node=>node.className==='directory-card').length,0);
 const records=Array.from({length:9},(_,i)=>({...base,slug:'prueba-'+i,nombre:'Prueba '+i,valoracionPublica:{promedio:1+i%5,total:2}}));
 const paged=await run('es',records,false,true);assert.equal(walk(paged,node=>node.className==='directory-card').length,6);
 const pagination=walk(paged,node=>node.className==='directory-pagination')[0];pagination.children[2].events.click();assert.equal(walk(paged,node=>node.className==='directory-card').length,3);
 const order=find(paged,'directory-order');order.value='reverse';order.events.change();
 assert(walk(paged,node=>node.tag==='a').some(node=>node.textContent==='Prueba 8'));
 assert.equal(walk(paged,node=>node.className==='directory-rating-summary').length,0);
 const failure=await run('es',[],true);assert(failure.textContent.includes('No podemos consultar'));
 console.log('OK: necesidades del viaje, búsqueda sin tildes, filtros combinados, orden alfabético, paginación, fichas ES/EN, teclado y publicaciones verificadas.');
})().catch(error=>{console.error(error);process.exitCode=1});
