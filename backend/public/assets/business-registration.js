(() => {
  const form = document.querySelector('#business-registration-form');
  if (!form) return;
  const destination = document.querySelector('#registration-destination');
  const category = document.querySelector('#registration-category');
  const search = document.querySelector('#registration-search');
  const results = document.querySelector('#registration-results');
  const missing = document.querySelector('#registration-not-found');
  const type = document.querySelector('#registration-application-type');
  const businessId = document.querySelector('#registration-business-id');
  const newNameWrap = document.querySelector('#registration-new-name-wrap');
  const newName = document.querySelector('#registration-new-name');
  const submit = document.querySelector('#registration-submit');
  const relationship = document.querySelector('#registration-relationship');
  const authorityWrap = document.querySelector('#registration-authority-wrap');
  const authorityInput = document.querySelector('#registration-authority-document');
  let timer;
  const selectionNotice=document.createElement('p');selectionNotice.className='notice business-claim-review-message';selectionNotice.setAttribute('role','status');selectionNotice.hidden=true;results.after(selectionNotice);
  const endpoint = `${form.dataset.searchUrl}?tipo=business&search=1`;

  function choose(mode, id = '', reviewMessage = '', isOperator = false) {
    selectionNotice.textContent=reviewMessage;selectionNotice.hidden=!reviewMessage;
    form.dataset.operatorEligible = isOperator ? '1' : '0';
    type.value = mode;
    businessId.value = id;
    const isNew = mode === 'new_business';
    for(const name of ['business_registration']){const field=form.querySelector(`[name="${name}"]`);if(field)field.required=!!mode;}
    if(authorityInput)authorityInput.required=isNew&&relationship?.value==='authorized_administrator';
    const details=document.querySelector('#registration-new-details');if(details){details.hidden=!isNew;details.disabled=!isNew;}
    if(isNew&&!newName.value.trim())newName.value=search.value.trim();
    newNameWrap.hidden = !isNew;
    newName.required = isNew;
    const stage=document.querySelector('#registration-plan-choice');if(stage){stage.hidden=!mode;stage.disabled=!mode;}
    const continuation=document.querySelector('#registration-continuation');if(continuation){continuation.hidden=!mode;continuation.disabled=!mode;}
    submit.disabled = !mode;
    form.dispatchEvent(new Event('operator-eligibility-change'));
    form.querySelectorAll('.business-registration-option input').forEach((radio) => {
      radio.checked = mode === 'existing_business' && radio.value === id;
    });
  }

  function syncFree(){const chosen=form.querySelector('[name="plan_code"]:checked')?.value;const free=chosen==='free';const continuation=document.querySelector('#registration-continuation');if(continuation){continuation.hidden=!type.value||!chosen;continuation.disabled=!type.value||!chosen;}submit.disabled=!type.value||!chosen;const period=form.querySelector('.subscription-choice-period');if(period){period.hidden=free||!chosen;for(const control of period.querySelectorAll('select'))control.disabled=free||!chosen;}const distinct=document.querySelector('#registration-distinct-business');if(distinct){distinct.hidden=!free||type.value!=='new_business';distinct.disabled=!free||type.value!=='new_business';}const holder=form.querySelector('[data-holder-identity]');if(holder){holder.hidden=free||!chosen;holder.disabled=free||!chosen;}const details=document.querySelector('#registration-new-details');if(details){details.hidden=free||!chosen||type.value!=='new_business';details.disabled=free||!chosen||type.value!=='new_business';}}
  form.addEventListener('change',event=>{if(event.target.name==='plan_code')syncFree();});
  form.addEventListener('operator-eligibility-change',syncFree);syncFree();

  relationship?.addEventListener('change', () => {
    const needsAuthority = type.value==='new_business'&&relationship.value === 'authorized_administrator';
    if(!authorityWrap||!authorityInput)return;
    authorityWrap.hidden = !needsAuthority;
    authorityInput.required = needsAuthority;
    if (!needsAuthority) authorityInput.value = '';
  });

  function renderBusinesses(items) {
    results.replaceChildren();
    if (!items.length) {
      results.innerHTML = '<p>No encontramos coincidencias. Puedes crear el negocio con su nombre, categoría y documento de respaldo.</p>';
      missing.disabled = false;
      choose('', '');
      return;
    }
    const intro = document.createElement('p');
    intro.textContent = 'Elige el negocio correcto. Si ya tiene un responsable, podrás solicitar una revisión sin modificar su acceso actual.';
    results.append(intro);
    for (const item of items) {
      const label = document.createElement('label');
      label.className = 'business-registration-option';
      const radio = document.createElement('input');
      radio.type = 'radio'; radio.name = 'registration_choice'; radio.value = String(item.id);
      radio.disabled = !item.can_request;
      const copy = document.createElement('span');
      const name = document.createElement('strong'); name.textContent = item.display_name;
      const state = document.createElement('small'); state.textContent = item.requires_review ? (item.review_message || 'Este negocio ya tiene un responsable o una solicitud. Puedes pedir una revisión; no obtendrás acceso automáticamente.') : item.can_request ? 'Sin responsable · puedes reclamarlo y administrarlo con tu cuenta' : 'No disponible para nuevas solicitudes en este momento';
      copy.append(name, state); label.append(radio, copy); results.append(label);
      radio.addEventListener('change', () => { choose('existing_business', String(item.id), item.requires_review ? (item.review_message || 'Tu solicitud necesita revisión y no cambia al responsable actual.') : '', !!item.is_experience_operator); missing.disabled = false; });
    }
    missing.disabled = false;
    choose('', '');
  }

  const categoryPaths={hospedaje:'M3 19V7h18v12M3 14h18M7 10h3m4 0h3',alimentacion:'M5 3v7m3-7v7m-5-4h7M6 10v11M17 3v18m0-18c5 3 5 8 0 8',licores:'M8 3h8v8a4 4 0 0 1-8 0V3m4 12v6m-4 0h8','vida-nocturna':'M16 3a9 9 0 1 0 5 14A8 8 0 0 1 16 3',comercio:'M4 8h16v13H4V8m3 0V6a5 5 0 0 1 10 0v2',salud:'M9 3h6v6h6v6h-6v6H9v-6H3V9h6V3',experiencias:'m2 20 7-14 5 9 3-5 5 10H2m4-8 3 2 3-2',cultura:'m3 9 9-6 9 6H3m2 2v8m7-8v8m7-8v8M3 21h18',transporte:'M4 16V7h16v9H4m3 0v4m10-4v4M4 12h16',servicios:'m4 20 9-9m-3-7a6 6 0 0 0 7 7l4-4-4 1-1-4a6 6 0 0 0-6 0',otro:'M4 4h6v6H4V4m10 0h6v6h-6V4M4 14h6v6H4v-6m10 0h6v6h-6v-6'};
  function categoryIcon(code){const svg=document.createElementNS('http://www.w3.org/2000/svg','svg');svg.setAttribute('viewBox','0 0 24 24');svg.setAttribute('class','category-icon');svg.setAttribute('aria-hidden','true');const path=document.createElementNS(svg.namespaceURI,'path');path.setAttribute('d',categoryPaths[code]||categoryPaths.otro);svg.append(path);return svg;}
  function syncExtraCategories(){for(const input of document.querySelectorAll('#registration-extra-categories input')){const primary=input.value===category.value;input.closest('label').hidden=primary;input.disabled=primary;if(primary)input.checked=false;}}
  async function loadCategories() {
    category.replaceChildren(new Option('Cargando categorías…', ''));
    category.disabled = true; search.disabled = true; missing.disabled = true; choose('', '');
    results.innerHTML = '<p>Selecciona la categoría del negocio.</p>';
    if (!destination.value) { category.replaceChildren(new Option('Selecciona una categoría', '')); return; }
    try {
      const response = await fetch(`${endpoint}&destination_id=${encodeURIComponent(destination.value)}&category=__categories__`, { headers: { Accept: 'application/json' } });
      const data = await response.json();
      category.replaceChildren(new Option('Selecciona una categoría', ''));
      for (const item of data.results || []) category.add(new Option(item.label, item.code));
      const extras=document.querySelector('#registration-extra-categories');if(extras){extras.replaceChildren();for(const item of data.results||[]){const label=document.createElement('label');const input=document.createElement('input');input.type='checkbox';input.name='category_codes[]';input.value=item.code;label.append(input,categoryIcon(item.code),document.createTextNode(item.label));extras.append(label);}}
      category.disabled = false;syncExtraCategories();
    } catch {
      category.replaceChildren(new Option('No se pudieron cargar las categorías', ''));
    }
  }

  async function searchBusinesses() {
    const term = search.value.trim();
    choose('', '');
    if (term.length < 2 || !destination.value || !category.value) {
      results.innerHTML = '<p>Escribe al menos dos letras para buscar.</p>'; missing.disabled = true; return;
    }
    results.innerHTML = '<p>Buscando…</p>';
    try {
      const response = await fetch(`${endpoint}&destination_id=${encodeURIComponent(destination.value)}&category=${encodeURIComponent(category.value)}&q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' } });
      const data = await response.json(); renderBusinesses(data.results || []);
    } catch {
      results.innerHTML = '<p>No se pudo completar la búsqueda. Inténtalo de nuevo.</p>'; missing.disabled = true;
    }
  }

  destination.addEventListener('change', loadCategories);
  category.addEventListener('change', () => { syncExtraCategories(); search.disabled = !category.value; search.value = ''; results.innerHTML = '<p>Escribe el nombre del negocio para buscarlo.</p>'; missing.disabled = true; choose('', ''); if (category.value) search.focus(); });
  search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(searchBusinesses, 250); });
  missing.addEventListener('click', () => choose('new_business'));
  form.addEventListener('submit', (event) => {
    if (!type.value || (type.value === 'existing_business' && !businessId.value) || (type.value === 'new_business' && !newName.value.trim())) {
      event.preventDefault(); results.focus();
    }
  });
  if (destination.value) {
    let initial={};try{initial=JSON.parse(form.dataset.registrationState||'{}');}catch{}
    loadCategories().then(()=>{if(initial.category&&Array.from(category.options).some(option=>option.value===initial.category)){category.value=initial.category;search.disabled=false;missing.disabled=false;for(const input of document.querySelectorAll('#registration-extra-categories input'))input.checked=Array.isArray(initial.extras)&&initial.extras.includes(input.value);syncExtraCategories();if(initial.type==='new_business')choose('new_business');}});
  }
})();
