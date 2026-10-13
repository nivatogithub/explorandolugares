(async () => {
  const container = document.getElementById('catalogo-local');
  if (!container) return;
  const scriptUrl = document.currentScript.src;
  const english = document.documentElement.lang === 'en';
  const portuguese = document.documentElement.lang === 'pt-BR';
  const root = new URL(english ? '../en/' : (portuguese ? '../pt-br/' : '../'), scriptUrl);
  const assets = new URL('./', scriptUrl);
  try {
    const responses = await Promise.all(['catalogo.json', 'contact-options.json'].map(path => fetch(new URL(path, scriptUrl))));
    if (responses.some(response => !response.ok)) throw new Error('catalogo');
    const [data, options] = await Promise.all(responses.map(response => response.json()));
    const types = new Map(options.groups.flatMap(group => group.categories.map(item => [item.value, item])));
    const validContact = url => {
      try { const parsed = new URL(url); return ['https:', 'http:'].includes(parsed.protocol) && parsed.hostname && !parsed.username && !parsed.password; }
      catch { return false; }
    };
    const records = (Array.isArray(data.publicaciones) ? data.publicaciones : []).filter(item =>
      item && item.destino === container.dataset.destino && item.registroConfirmado === true &&
      item.informacionVerificada === true && item.estado === 'publicado' && types.has(item.tipoServicio) &&
      typeof item.slug === 'string' && /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(item.slug) &&
      ['nombre', 'descripcion', 'descripcionEn'].every(key => typeof item[key] === 'string' && item[key].trim()) &&
      typeof item.contactoUrl === 'string' && validContact(item.contactoUrl)
    );
    const label = type => english ? types.get(type).en : (portuguese ? types.get(type).pt : types.get(type).label);
    const tabs = document.createElement('div'); tabs.className = 'directory-tabs';
    tabs.setAttribute('role', 'tablist'); tabs.setAttribute('aria-label', english ? 'Service types' : (portuguese ? 'Tipos de serviço' : 'Tipos de servicio'));
    const panel = document.createElement('div'); panel.id = 'directory-panel';
    panel.setAttribute('role', 'tabpanel'); panel.tabIndex = 0;
    const status = document.createElement('p'); status.className = 'directory-count';
    status.setAttribute('role', 'status');
    const buttons = [];
    const travelGroups = options.travelGroups;
    const categories = ['todos', ...travelGroups.filter(group => records.some(item => group.types.includes(item.tipoServicio))).map(group => group.value)];
    const groupLabel = key => { const group = travelGroups.find(group => group.value === key); return english ? group.en : (portuguese ? group.pt : group.label); };
    const inGroup = (item, group) => group === 'todos' || travelGroups.find(value => value.value === group).types.includes(item.tipoServicio);
    const controls = document.createElement('div'); controls.className = 'directory-filters';
    const field = (id, text, tag) => {
      const wrapper = document.createElement('label'); wrapper.setAttribute('for', id); wrapper.textContent = text;
      const input = document.createElement(tag); input.id = id; wrapper.append(input); controls.append(wrapper); return input;
    };
    const option = (value, text) => { const node = document.createElement('option'); node.value = value; node.textContent = text; return node; };
    const search = field('directory-search', english ? 'Search' : 'Buscar', 'input');
    search.type = 'search'; search.maxLength = 120; search.value = '';
    search.placeholder = english ? 'Name, service or keyword' : (portuguese ? 'Nome, serviço ou palavra' : 'Nombre, servicio o palabra');
    const service = field('directory-service', english ? 'Service type' : (portuguese ? 'Tipo de serviço' : 'Tipo de servicio'), 'select'); service.value = '';
    const order = field('directory-order', english ? 'Sort by' : 'Ordenar por', 'select');
    order.append(option('name', english ? 'Name A–Z' : (portuguese ? 'Nome A–Z' : 'Nombre A–Z')));
    order.append(option('reverse', english ? 'Name Z–A' : (portuguese ? 'Nome Z–A' : 'Nombre Z–A'))); order.value = 'name';
    const pagination = document.createElement('div'); pagination.className = 'directory-pagination';
    const previous = document.createElement('button'); previous.type = 'button'; previous.textContent = english ? 'Previous' : 'Anterior';
    const next = document.createElement('button'); next.type = 'button'; next.textContent = english ? 'Next' : (portuguese ? 'Próximo' : 'Siguiente');
    const pageLabel = document.createElement('span'); pagination.append(previous, pageLabel, next);
    let active = 'todos', page = 0;
    const pageSize = 6;
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    function card(item) {
      const article = document.createElement('article'); article.className = 'directory-card';
      const href = new URL(`destinos/${container.dataset.destino}/lugares/${item.slug}/index.html`, root).href;
      const name = english ? (item.nombreEn || item.nombre) : (portuguese ? (item.nombrePt || item.nombre) : item.nombre);
      const picture = document.createElement('a'); picture.className = 'directory-picture'; picture.href = href;
      picture.tabIndex = -1; picture.setAttribute('aria-hidden', 'true');
      const fallback = () => {
        const initials = document.createElement('span'); initials.textContent = name.trim().slice(0, 1).toUpperCase();
        picture.replaceChildren(initials);
      };
      fallback();
      if (typeof item.imagen === 'string' && item.imagen.startsWith('assets/')) {
        const src = new URL('../' + item.imagen, assets);
        if (src.origin === assets.origin && src.pathname.startsWith(assets.pathname)) {
          const photo = document.createElement('img'); photo.src = src.href; photo.alt = '';
          photo.loading = 'lazy'; photo.width = 640; photo.height = 400;
          photo.addEventListener('error', fallback, {once: true}); picture.replaceChildren(photo);
        }
      }
      const body = document.createElement('div'); body.className = 'directory-card-body';
      const category = document.createElement('p'); category.className = 'eyebrow'; category.textContent = label(item.tipoServicio);
      const title = document.createElement('h3');
      const link = document.createElement('a'); link.href = href; link.textContent = name; title.append(link);
      const description = document.createElement('p'); description.className = 'directory-description';
      description.textContent = english ? item.descripcionEn : (portuguese ? (item.descripcionPt || item.descripcion) : item.descripcion);
      const action = document.createElement('a'); action.className = 'text-link'; action.href = href;
      action.textContent = english ? 'View details ↗' : (portuguese ? 'Ver detalhes ↗' : 'Ver negocio ↗');
      action.setAttribute('aria-label', (english ? 'View details: ' : (portuguese ? 'Ver detalhes: ' : 'Ver negocio: ')) + name);
      body.append(category, title, description);
      body.append(action); article.append(picture, body); return article;
    }
    function render() {
      const words = normalize(search.value.trim()).split(/\s+/).filter(Boolean);
      const visible = records.filter(item => {
        const haystack = normalize([item.nombre, item.nombreEn || '', item.nombrePt || '', item.descripcion, item.descripcionEn, item.descripcionPt || '', label(item.tipoServicio)].join(' '));
        return inGroup(item, active) && (!service.value || item.tipoServicio === service.value) && words.every(word => haystack.includes(word));
      }).sort((a, b) => {

        const comparison = (english ? (a.nombreEn || a.nombre) : a.nombre).localeCompare(english ? (b.nombreEn || b.nombre) : b.nombre, english ? 'en' : 'es');
        return order.value === 'reverse' ? -comparison : comparison;
      });
      const pages = Math.max(1, Math.ceil(visible.length / pageSize)); page = Math.min(page, pages - 1);
      status.textContent = visible.length ? (english ? `${visible.length} ${visible.length === 1 ? 'place' : 'places'}` : `${visible.length} ${visible.length === 1 ? 'lugar' : 'lugares'}`) : '';
      pagination.hidden = visible.length <= pageSize; previous.disabled = page === 0; next.disabled = page >= pages - 1;
      pageLabel.textContent = english ? `Page ${page + 1} of ${pages}` : `Página ${page + 1} de ${pages}`;
      if (!visible.length) {
        const empty = document.createElement('p'); empty.className = 'directory-empty';
        empty.textContent = records.length
          ? (english ? 'No matches. Try other keywords or filters.' : (portuguese ? 'Não há resultados. Tente outras palavras ou filtros.' : 'No hay coincidencias. Prueba otras palabras o filtros.'))
          : (english ? 'There are no places published in this directory yet.' : (portuguese ? 'Ainda não há locais publicados neste diretório.' : 'Aún no hay lugares publicados en este directorio.'));
        panel.replaceChildren(empty); return;
      }
      const grid = document.createElement('div'); grid.className = 'directory-grid';
      grid.append(...visible.slice(page * pageSize, (page + 1) * pageSize).map(card)); panel.replaceChildren(grid);
    }
    function select(type, focus = false) {
      active = type; page = 0;
      for (const button of buttons) {
        const selected = button.dataset.type === type;
        button.setAttribute('aria-selected', String(selected)); button.tabIndex = selected ? 0 : -1;
        if (selected) { panel.setAttribute('aria-labelledby', button.id); if (focus) button.focus(); }
      }
      service.replaceChildren(option('', english ? 'All services' : (portuguese ? 'Todos os serviços' : 'Todos los servicios'))); service.value = ''; if (typeof Event === 'function') service.dispatchEvent(new Event('floating-select:refresh'));
      for (const key of types.keys()) if (records.some(item => inGroup(item, active) && item.tipoServicio === key)) service.append(option(key, label(key)));
      render();
    }
    search.addEventListener('input', () => { page = 0; render(); });
    for (const input of [service, order]) input.addEventListener('change', () => { page = 0; render(); });
    previous.addEventListener('click', () => { if (page > 0) { page--; render(); panel.focus(); } });
    next.addEventListener('click', () => { page++; render(); panel.focus(); });
    for (const type of categories) {
      const button = document.createElement('button'); button.type = 'button'; button.id = `directory-tab-${type}`;
      button.dataset.type = type; button.setAttribute('role', 'tab'); button.setAttribute('aria-controls', panel.id);
      button.textContent = type === 'todos' ? (english ? 'All' : (portuguese ? 'Todos' : 'Todos')) : groupLabel(type);
      button.addEventListener('click', () => select(type));
      button.addEventListener('keydown', event => {
        const index = buttons.indexOf(button);
        let next;
        if (event.key === 'ArrowRight') next = (index + 1) % buttons.length;
        if (event.key === 'ArrowLeft') next = (index - 1 + buttons.length) % buttons.length;
        if (event.key === 'Home') next = 0;
        if (event.key === 'End') next = buttons.length - 1;
        if (next !== undefined) { event.preventDefault(); select(buttons[next].dataset.type, true); }
      });
      buttons.push(button); tabs.append(button);
    }
    container.replaceChildren(tabs, controls, status, panel, pagination); select('todos');
  } catch {
    container.textContent = english ? 'We cannot load the directory right now. Please try again later.' : (portuguese ? 'Não é possível consultar o diretório neste momento. Tente novamente mais tarde.' : 'No podemos consultar el catálogo en este momento. Intenta nuevamente más tarde.');
  }
})();
