/* Local search and ordering affect only the rows already displayed; never write data. */
(()=>{
 for(const table of document.querySelectorAll('.destination-admin-table table,.health-table table,.admin-incidents-grid table')){
  const body=table.tBodies[0],rows=body?[...body.rows]:[];
  if(rows.length<2||rows.some(row=>row.cells.length!==table.tHead?.rows[0]?.cells.length))continue;
  const tools=document.createElement('div');tools.className='admin-grid-tools';const label=document.createElement('label');label.textContent='Buscar en esta tabla';const input=document.createElement('input');input.type='search';input.placeholder='Escribe para filtrar';const count=document.createElement('span');count.setAttribute('role','status');label.append(input);tools.append(label,count);table.parentElement.before(tools);
  const norm=text=>text.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
  function filter(){const value=norm(input.value);let visible=0;for(const row of rows){row.hidden=!norm(row.innerText).includes(value);if(!row.hidden)visible++;}count.textContent=visible+' de '+rows.length+' registros de esta tabla';}
  input.addEventListener('input',filter);filter();
  for(const [index,cell] of [...table.tHead.rows[0].cells].entries()){
   if(/acción|acciones|herramientas|atención|decisión|detalle|revisión/i.test(cell.textContent))continue;
   const button=document.createElement('button');button.type='button';button.className='admin-grid-sort';button.textContent=cell.textContent;cell.replaceChildren(button);let asc=true;
   button.addEventListener('click',()=>{for(const th of table.tHead.rows[0].cells)th.removeAttribute('aria-sort');cell.setAttribute('aria-sort',asc?'ascending':'descending');const direction=asc?1:-1;rows.sort((a,b)=>direction*a.cells[index].innerText.localeCompare(b.cells[index].innerText,'es',{numeric:true,sensitivity:'base'}));for(const row of rows)body.append(row);asc=!asc;});
  }
 }
})();
