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
  let timer;
  const endpoint = '/v1/negocios/registro/?search=1';

  function choose(mode, id = '') {
    type.value = mode;
    businessId.value = id;
    const isNew = mode === 'new_business';
    newNameWrap.hidden = !isNew;
    newName.required = isNew;
    submit.disabled = !mode;
    form.querySelectorAll('.business-registration-option input').forEach((radio) => {
      radio.checked = mode === 'existing_business' && radio.value === id;
    });
  }

  function renderBusinesses(items) {
    results.replaceChildren();
    if (!items.length) {
      results.innerHTML = '<p>No encontramos coincidencias con ese nombre. Si confirmas que no aparece, solicita una revisión para agregarlo.</p>';
      missing.disabled = false;
      choose('', '');
      return;
    }
    const intro = document.createElement('p');
    intro.textContent = 'Elige el negocio correcto. Los que ya tienen una solicitud o representante aparecen como no disponibles.';
    results.append(intro);
    for (const item of items) {
      const label = document.createElement('label');
      label.className = 'business-registration-option';
      const radio = document.createElement('input');
      radio.type = 'radio'; radio.name = 'registration_choice'; radio.value = String(item.id);
      radio.disabled = !item.can_request;
      const copy = document.createElement('span');
      const name = document.createElement('strong'); name.textContent = item.display_name;
      const state = document.createElement('small'); state.textContent = item.can_request ? 'Está en nuestros registros · puedes solicitar asociarlo' : 'Ya tiene una solicitud o una cuenta asociada';
      copy.append(name, state); label.append(radio, copy); results.append(label);
      radio.addEventListener('change', () => { choose('existing_business', item.id); missing.disabled = false; });
    }
    missing.disabled = false;
    choose('', '');
  }

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
      category.disabled = false;
    } catch {
      category.replaceChildren(new Option('No se pudieron cargar las categorías', ''));
    }
  }

  async function searchBusinesses() {
    const term = search.value.trim();
    choose('', '');
    if (term.length < 2 || !destination.value || !category.value) {
      results.innerHTML = '<p>Escribe al menos dos letras para buscar.</p>'; missing.disabled = !(destination.value && category.value); return;
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
  category.addEventListener('change', () => { search.disabled = !category.value; search.value = ''; results.innerHTML = '<p>Escribe el nombre del negocio para buscarlo.</p>'; missing.disabled = !category.value; choose('', ''); if (category.value) search.focus(); });
  search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(searchBusinesses, 250); });
  missing.addEventListener('click', () => choose('new_business'));
  form.addEventListener('submit', (event) => {
    if (!type.value || (type.value === 'existing_business' && !businessId.value) || (type.value === 'new_business' && !newName.value.trim())) {
      event.preventDefault(); results.focus();
    }
  });
  if (destination.value) loadCategories();
})();
