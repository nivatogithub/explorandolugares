(() => {
  'use strict';
  const openMenus=new Set();let sequence=0;
  const locale=document.documentElement.lang.startsWith('en')?'en':document.documentElement.lang.startsWith('pt')?'pt':'es';
  const copy={es:['Mostrar opciones','No hay coincidencias.','Selecciona','Elige una opción de la lista.'],en:['Show options','No matches.','Select','Choose an option from the list.'],pt:['Mostrar opções','Nenhum resultado.','Selecione','Escolha uma opção da lista.']}[locale];
  const normalize=value=>value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase(locale).trim();
  function closeAll(except){for(const control of [...openMenus])if(control!==except)control.close();}
  function enhance(select){
    if(select.hasAttribute('data-native-select')||select.dataset.floatingSelect==='ready'||select.multiple||select.size>1)return;
    select.dataset.floatingSelect='ready';
    const wrapper=document.createElement('span');wrapper.className='floating-select';select.before(wrapper);wrapper.append(select);
    select.classList.add('floating-select-native');select.tabIndex=-1;select.setAttribute('aria-hidden','true');
    const row=document.createElement('span');row.className='floating-select-control';
    const input=document.createElement('input');input.type='text';input.className='floating-select-trigger floating-select-combobox';input.autocomplete='off';input.spellcheck=false;input.setAttribute('role','combobox');input.setAttribute('aria-autocomplete','list');input.setAttribute('aria-haspopup','listbox');input.setAttribute('aria-expanded','false');
    const toggle=document.createElement('button');toggle.type='button';toggle.className='floating-select-toggle';toggle.setAttribute('aria-label',copy[0]);toggle.setAttribute('aria-expanded','false');
    const arrow=document.createElement('span');arrow.className='floating-select-arrow';arrow.setAttribute('aria-hidden','true');arrow.textContent='⌄';toggle.append(arrow);row.append(input,toggle);
    const menu=document.createElement('div');menu.className='floating-select-menu';menu.hidden=true;
    const options=document.createElement('div');options.className='floating-select-options';options.setAttribute('role','listbox');options.id='floating-options-'+(++sequence);input.setAttribute('aria-controls',options.id);toggle.setAttribute('aria-controls',options.id);
    const empty=document.createElement('p');empty.className='floating-select-empty';empty.setAttribute('role','status');empty.textContent=copy[1];empty.hidden=true;
    menu.append(options,empty);wrapper.append(row,menu);
    let editing=false,query='',restoringFocus=false;
    function focusClosed(){restoringFocus=true;input.focus();input.select();restoringFocus=false;}
    const control={wrapper,close(){menu.hidden=true;input.setAttribute('aria-expanded','false');toggle.setAttribute('aria-expanded','false');wrapper.classList.remove('is-open');openMenus.delete(control);},open(){if(select.disabled||select.hidden)return;closeAll(control);refresh();menu.hidden=false;input.setAttribute('aria-expanded','true');toggle.setAttribute('aria-expanded','true');wrapper.classList.add('is-open');openMenus.add(control);}};
    function choose(value){editing=false;query='';input.setCustomValidity('');select.value=value;select.dispatchEvent(new Event('input',{bubbles:true}));select.dispatchEvent(new Event('change',{bubbles:true}));control.close();focusClosed();}
    function filter(){let count=0;for(const option of options.children){option.hidden=!option.dataset.filterText.includes(query);if(!option.hidden)count++;}empty.hidden=count>0;}
    function refresh(){
      wrapper.hidden=select.hidden;if(select.hidden)control.close();
      const selected=select.options[select.selectedIndex]||select.options[0];
      if(!editing){input.value=select.value?(selected?.textContent||''):'';input.placeholder=select.options[0]?.value===''?select.options[0].textContent:copy[2];input.setCustomValidity('');}
      input.disabled=select.disabled;toggle.disabled=select.disabled;input.required=select.required;
      const label=select.closest('label'),caption=label?.querySelector('[data-region-label],[data-city-label]')?.textContent||Array.from(label?.childNodes||[]).filter(node=>node.nodeType===3).map(node=>node.textContent).join('').trim();
      input.setAttribute('aria-label',select.getAttribute('aria-label')||caption||copy[2]);
      options.replaceChildren();
      for(const option of select.options){if(option.hidden)continue;const item=document.createElement('button');item.type='button';item.className='floating-select-option';item.setAttribute('role','option');item.setAttribute('aria-selected',String(option.selected));item.disabled=option.disabled;item.tabIndex=-1;item.dataset.filterText=normalize(option.textContent);item.textContent=option.textContent;if(option.selected){const check=document.createElement('span');check.setAttribute('aria-hidden','true');check.textContent='✓';item.append(check);}item.addEventListener('click',()=>choose(option.value));options.append(item);}
      filter();
    }
    input.addEventListener('focus',()=>{if(restoringFocus)return;control.open();input.select();});
    input.addEventListener('click',()=>{if(menu.hidden)control.open();});
    input.addEventListener('input',()=>{editing=true;query=normalize(input.value);input.setCustomValidity(copy[3]);control.open();filter();});
    input.addEventListener('keydown',event=>{
      if(event.key==='Escape'){event.preventDefault();event.stopPropagation();editing=false;query='';refresh();control.close();return;}
      if(event.key==='Tab'){control.close();return;}
      const items=Array.from(options.querySelectorAll('button:not(:disabled)')).filter(item=>!item.hidden);
      if(['ArrowDown','ArrowUp'].includes(event.key)){event.preventDefault();if(menu.hidden)control.open();(event.key==='ArrowDown'?items[0]:items.at(-1))?.focus();}
      if(event.key==='Enter'){event.preventDefault();if(menu.hidden){control.open();return;}const chosen=editing?items[0]:items.find(item=>item.getAttribute('aria-selected')==='true')||items[0];chosen?.click();}
    });
    toggle.addEventListener('click',()=>{if(menu.hidden){input.focus();control.open();input.select();}else control.close();});
    menu.addEventListener('keydown',event=>{
      const items=Array.from(options.querySelectorAll('button:not(:disabled)')).filter(item=>!item.hidden),current=items.indexOf(document.activeElement);
      if(event.key==='Escape'){event.preventDefault();event.stopPropagation();control.close();focusClosed();return;}
      if(event.key==='Tab'){control.close();return;}
      if(['ArrowDown','ArrowUp'].includes(event.key)){event.preventDefault();items[(current+(event.key==='ArrowDown'?1:-1)+items.length)%items.length]?.focus();}
      if(event.key==='Home'){event.preventDefault();items[0]?.focus();}
      if(event.key==='End'){event.preventDefault();items.at(-1)?.focus();}
      if(event.key.length===1&&!event.ctrlKey&&!event.metaKey&&!event.altKey&&event.key!==' '){event.preventDefault();input.focus();input.value=event.key;input.dispatchEvent(new Event('input',{bubbles:true}));}
    });
    select.addEventListener('change',()=>{editing=false;query='';refresh();});
    select.addEventListener('focus',()=>input.focus());
    select.addEventListener('invalid',event=>{event.preventDefault();input.setCustomValidity(copy[3]);input.focus();input.reportValidity();});
    select.addEventListener('floating-select:refresh',()=>{editing=false;query='';refresh();});
    wrapper.addEventListener('focusout',event=>{if(!wrapper.contains(event.relatedTarget))control.close();});
    new MutationObserver(()=>{editing=false;query='';refresh();}).observe(select,{childList:true,subtree:true,attributes:true});refresh();
  }
  function scan(root=document){root.querySelectorAll?.('select').forEach(enhance);}
  document.addEventListener('pointerdown',event=>{if(![...openMenus].some(control=>event.target.closest('.floating-select')===control.wrapper))closeAll();});
  new MutationObserver(records=>records.forEach(record=>record.addedNodes.forEach(node=>{if(node.nodeType===1)scan(node);}))).observe(document.documentElement,{childList:true,subtree:true});
  document.addEventListener('DOMContentLoaded',()=>scan());
})();
