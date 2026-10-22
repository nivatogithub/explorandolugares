(() => {
  const grid = document.getElementById('destination-grid');
  if (!grid) return;
  const cards = [...grid.querySelectorAll('.destination-card')];
  const search = document.getElementById('destination-search');
  const region = document.getElementById('destination-region');
  const status = document.getElementById('destination-results');
  const previous = document.getElementById('destination-previous');
  const next = document.getElementById('destination-next');
  const english = document.documentElement.lang === 'en';
  const portuguese = document.documentElement.lang === 'pt-BR';
  const normalize = text => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
  const pageSize = 9;
  const stateKey='el-place-list:'+location.pathname;
  let saved={};try{saved=JSON.parse(sessionStorage.getItem(stateKey)||'{}');}catch{}
  const params=new URL(location.href).searchParams;
  search.value=params.get('q')??saved.q??'';
  const savedRegion=params.get('region')??saved.region??'';
  if([...region.options].some(option=>option.value===savedRegion))region.value=savedRegion;
  let page=Math.max(0,(parseInt(params.get('pagina')??saved.page??'1',10)||1)-1);
  function render() {
    const term = normalize(search.value);
    const matches = cards.filter(card => normalize(card.dataset.name).includes(term) && (!region.value || card.dataset.region === region.value));
    const totalPages = Math.max(1, Math.ceil(matches.length / pageSize));
    page = Math.min(page, totalPages - 1);
    const visible = new Set(matches.slice(page * pageSize, (page + 1) * pageSize));
    cards.forEach(card => { card.hidden = !visible.has(card); });
    status.textContent = matches.length ? `${matches.length} ${english ? (matches.length === 1 ? 'destination' : 'destinations') : (matches.length === 1 ? 'destino' : 'destinos')}` : (english ? 'No destinations match these filters.' : (portuguese ? 'Nenhum destino corresponde a estes filtros.' : 'No hay destinos que coincidan con estos filtros.'));
    const state={q:search.value,region:region.value,page:page+1};
    try{sessionStorage.setItem(stateKey,JSON.stringify(state));}catch{}
    const url=new URL(location.href);
    for(const [key,value] of [['q',state.q],['region',state.region],['pagina',state.page>1?String(state.page):'']]){
      if(value)url.searchParams.set(key,value);else url.searchParams.delete(key);
    }
    history.replaceState(null,'',url);
    previous.disabled = page === 0;
    next.disabled = page >= totalPages - 1;
    document.getElementById('destination-pagination').hidden = totalPages <= 1;
    document.getElementById('destination-page-number').textContent = `${english ? 'Page' : 'Página'} ${page + 1} ${english ? 'of' : 'de'} ${totalPages}`;
  }
  function filter() { page = 0; render(); }
  search.addEventListener('input', filter);
  region.addEventListener('change', filter);
  document.getElementById('destination-clear').addEventListener('click', () => { search.value = ''; region.value = ''; region.dispatchEvent(new Event('change', {bubbles: true})); search.focus(); });
  function move(delta) { page += delta; render(); grid.scrollIntoView({block: 'start'}); }
  previous.addEventListener('click', () => move(-1));
  next.addEventListener('click', () => move(1));
  document.getElementById('destination-controls').hidden = false;
  render();
})();
