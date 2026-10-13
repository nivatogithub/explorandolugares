const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict');
const source = fs.readFileSync('ensayos/v1/assets/destinos.js', 'utf8');
const records = [{name:'Jericó',region:'Suroeste'}, ...JSON.parse(fs.readFileSync('content/destinos-piloto.json','utf8')).destinations];
function check(lang) {
  const nodes = {};
  for (const id of ['destination-grid','destination-search','destination-region','destination-results','destination-previous','destination-next','destination-pagination','destination-page-number','destination-controls','destination-clear']) {
    nodes[id] = {value:'',hidden:true,disabled:false,listeners:{},addEventListener(type,fn){this.listeners[type]=fn},focus(){},scrollIntoView(){}};
  }
  const cards = records.map(d=>({dataset:{name:d.name,region:d.region},hidden:false}));
  nodes['destination-grid'].querySelectorAll = () => cards;
  vm.runInNewContext(source,{document:{documentElement:{lang},getElementById:id=>nodes[id]}});
  const visible=()=>cards.filter(c=>!c.hidden).map(c=>c.dataset.name);
  assert.equal(visible().length,9);
  nodes['destination-next'].listeners.click();assert.equal(visible().length,9);assert(!visible().includes('Jericó'));
  nodes['destination-next'].listeners.click();assert.equal(visible().length,3);assert(nodes['destination-next'].disabled);
  nodes['destination-search'].value='medellin';nodes['destination-search'].listeners.input();assert.deepEqual(visible(),['Medellín']);
  assert(nodes['destination-pagination'].hidden);
  nodes['destination-region'].value='Oriente';nodes['destination-region'].listeners.change();assert.equal(visible().length,0);
  nodes['destination-clear'].listeners.click();assert.equal(visible().length,9);
  nodes['destination-region'].value='Urabá';nodes['destination-region'].listeners.change();assert.deepEqual(visible(),['Necoclí','Arboletes']);
  nodes['destination-search'].value='NECOCLI';nodes['destination-search'].listeners.input();assert.deepEqual(visible(),['Necoclí']);
  assert.equal(nodes['destination-results'].textContent,lang==='en'?'1 destination':'1 destino');
}
check('es');check('en');console.log('OK: 21 destinos, búsqueda sin tildes, filtros combinados, paginación 9/9/3 y reinicio ES/EN.');
