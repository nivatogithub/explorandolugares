(async () => {
  const form = document.getElementById('business-registration');
  if (!form) return;
  const source = new URL('contact-options.json', document.currentScript.src);
  const english = document.documentElement.lang === 'en';
  const portuguese = document.documentElement.lang === 'pt-BR';
  const category = form.elements.category;
  const municipality = form.elements.municipality;
  const requestedTown = new URL(document.URL).searchParams.get('municipio');
  const selectRequestedTown = () => {
    if (!municipality.value && [...municipality.options].some(item => item.value === requestedTown)) {
      municipality.value = requestedTown;
    }
  };
  selectRequestedTown();
  const option = (value, label) => {
    const node = document.createElement('option');
    node.value = value;
    node.textContent = label;
    return node;
  };
  try {
    const response = await fetch(source, {cache: 'no-cache'});
    if (!response.ok) throw new Error('options');
    const data = await response.json();
    if (!Array.isArray(data.municipalities) || !data.municipalities.length ||
        !data.municipalities.every(value => typeof value === 'string' && value.trim()) ||
        !Array.isArray(data.groups) || !data.groups.length ||
        !data.groups.every(group => typeof group.label === 'string' && typeof group.en === 'string' && typeof group.pt === 'string' &&
          Array.isArray(group.categories) && group.categories.length && group.categories.every(item =>
            typeof item.value === 'string' && item.value && typeof item.label === 'string' && typeof item.en === 'string' && typeof item.pt === 'string'))) {
      throw new Error('invalid-options');
    }
    const categories = document.createDocumentFragment();
    categories.append(option('', english ? 'Select an option' : (portuguese ? 'Selecione uma opção' : 'Selecciona una opción')));
    for (const group of data.groups) {
      const node = document.createElement('optgroup');
      node.label = english ? group.en : (portuguese ? group.pt : group.label);
      for (const item of group.categories) node.append(option(item.value, english ? item.en : (portuguese ? item.pt : item.label)));
      categories.append(node);
    }
    const towns = document.createDocumentFragment();
    towns.append(option('', english ? 'Select your municipality' : (portuguese ? 'Selecione seu município' : 'Selecciona tu municipio')));
    for (const name of data.municipalities) towns.append(option(name, name));
    // Conservar lo que la persona haya seleccionado mientras se cargaba el JSON.
    const selectedCategory = category.value;
    const selectedTown = municipality.value;
    category.replaceChildren(categories);
    municipality.replaceChildren(towns);
    if (data.groups.some(group => group.categories.some(item => item.value === selectedCategory))) category.value = selectedCategory;
    if (data.municipalities.includes(selectedTown)) municipality.value = selectedTown;
    selectRequestedTown();
  } catch {
    // Las opciones estáticas equivalentes permiten continuar si falla la descarga.
    // No se afecta el control independiente de disponibilidad del envío.
  }
})();
