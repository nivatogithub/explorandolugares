(() => {
  const $ = (id) => document.getElementById(id);
  const address = $('business-address');
  const reference = $('business-location-reference');
  const destination = $('business-destination');
  const open = $('business-map-open');
  const dialog = $('business-map-dialog');
  const close = $('business-map-close');
  const cancel = $('business-map-cancel');
  const retry = $('business-map-retry');
  const use = $('business-map-use');
  const mapNode = $('business-location-map');
  const selection = $('business-map-selection');
  const status = $('location-capture-status');
  const mapUrl = $('business-map-url');
  const latInput = $('business-latitude');
  const lonInput = $('business-longitude');
  const confirmed = $('business-location-confirmed');
  const mapChanged = $('business-location-changed');
  if (!address || !destination || !open || !dialog || !mapNode) return;

  let map = null;
  let marker = null;
  let draft = null;
  let lastReverseLookup = 0;
  let mapLayer = null;
  let mapReady = false;
  let destinationCenterPending = false;
  let mapLoadTimer = 0;

  const parseExisting = () => {
    if (!latInput.value.trim() || !lonInput.value.trim()) return null;
    const point = [Number(latInput.value), Number(lonInput.value)];
    return point.every(Number.isFinite) ? point : null;
  };

  const selectedDestination = () => {
    const option = destination.selectedOptions[0];
    const lat = Number(option?.dataset.lat);
    const lon = Number(option?.dataset.lon);
    return Number.isFinite(lat) && Number.isFinite(lon) && option?.dataset.lat !== '' && option?.dataset.lon !== ''
      ? [lat, lon]
      : null;
  };

  const selectedDestinationName = () => destination.selectedOptions[0]?.dataset.name?.trim() || '';

  const setMapCenter = (point, zoom = 14) => {
    if (!map) return;
    mapReady = false;
    map.setView(point, zoom);
    window.clearTimeout(mapLoadTimer);
    mapLoadTimer = window.setTimeout(() => {
      if (!mapReady) showMapFailure();
    }, 10000);
  };

  const geocodeDestination = async () => {
    const storedPoint = selectedDestination();
    if (storedPoint) return storedPoint;
    const name = selectedDestinationName();
    if (!name) return null;

    const cacheKey = `el-destination-center:${name.toLocaleLowerCase('es')}:co`;
    try {
      const cached = JSON.parse(sessionStorage.getItem(cacheKey) || 'null');
      if (Array.isArray(cached) && cached.length === 2 && cached.every(Number.isFinite)) return cached;
    } catch { /* Storage can be disabled; geocoding can still continue. */ }

    const params = new URLSearchParams({
      city: name,
      state: 'Antioquia',
      country: 'Colombia',
      countrycodes: 'co',
      format: 'jsonv2',
      limit: '1',
    });
    const response = await fetch(`https://nominatim.openstreetmap.org/search?${params}`, {
      headers: { Accept: 'application/json' },
      signal: AbortSignal.timeout(7000),
    });
    if (!response.ok) throw new Error('No se pudo encontrar el destino.');
    const results = await response.json();
    const result = results[0];
    const point = result ? [Number(result.lat), Number(result.lon)] : null;
    if (!point || !point.every(Number.isFinite)) throw new Error('No se encontraron coordenadas para este destino.');
    try { sessionStorage.setItem(cacheKey, JSON.stringify(point)); } catch { /* Optional cache. */ }
    return point;
  };

  const centerOnDestination = async () => {
    if (!map || parseExisting()) return;
    const name = selectedDestinationName();
    if (!name) return;
    destinationCenterPending = true;
    selection.textContent = `Buscando el centro de ${name} para mostrar el municipio correcto…`;
    try {
      const point = await geocodeDestination();
      if (!point || !map) throw new Error('No se encontraron coordenadas para este destino.');
      destinationCenterPending = false;
      setMapCenter(point, 16);
      selection.textContent = `Mapa centrado en ${name}. Haz clic en la ubicación exacta del negocio.`;
    } catch (error) {
      destinationCenterPending = false;
      selection.textContent = `${error.message} Puedes centrar el mapa en tu ubicación o moverlo manualmente antes de marcar el negocio.`;
    }
  };

  const updateDraft = (point) => {
    if (destinationCenterPending) {
      selection.textContent = 'Espera a que el mapa centre el destino seleccionado antes de marcar el negocio.';
      return;
    }
    if (!mapReady) {
      selection.textContent = 'El mapa no está disponible todavía. Espera a que cargue antes de seleccionar una ubicación.';
      return;
    }
    draft = point;
    const [lat, lon] = point;
    selection.textContent = `Punto seleccionado: ${lat.toFixed(6)}, ${lon.toFixed(6)}.`;
    use.disabled = false;
    if (marker) marker.setLatLng(point);
    else if (map) marker = L.marker(point, { draggable: true }).addTo(map).on('dragend', (event) => updateDraft(event.target.getLatLng().wrap().toArray()));
  };

  const showMapFailure = () => {
    mapReady = false;
    draft = null;
    use.disabled = true;
    selection.textContent = 'No se pudo cargar OpenStreetMap. Revisa tu conexión y vuelve a intentar; confirma la ubicación solo cuando puedas ver el mapa.';
    retry.hidden = false;
  };

  const ensureMap = () => {
    if (!window.L) {
      selection.textContent = 'No se pudo cargar el mapa. Revisa la conexión e inténtalo de nuevo.';
      return false;
    }
    const existing = parseExisting();
    const destinationPoint = selectedDestination();
    const initialCenter = existing || destinationPoint || [4.57, -74.29];
    if (!map) {
      map = L.map(mapNode, { scrollWheelZoom: true }).setView(initialCenter, existing ? 17 : destinationPoint ? 16 : 5);
      mapLayer = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        referrerPolicy: 'strict-origin-when-cross-origin',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
      });
      mapLayer.on('loading', () => {
        mapReady = false;
        window.clearTimeout(mapLoadTimer);
        mapLoadTimer = window.setTimeout(() => showMapFailure(), 10000);
      });
      mapLayer.on('tileload', () => {
        mapReady = true;
        window.clearTimeout(mapLoadTimer);
        retry.hidden = true;
        if (draft) updateDraft(draft);
        else if (!destinationCenterPending) selection.textContent = 'Haz clic en el punto exacto. Puedes arrastrar el mapa y acercarlo con los controles.';
      });
      mapLayer.on('load', () => { if (!mapReady) showMapFailure(); });
      mapLayer.addTo(map);
      map.on('click', (event) => updateDraft(event.latlng.wrap().toArray()));
    } else {
      setMapCenter(initialCenter, existing ? 17 : destinationPoint ? 16 : 5);
    }
    draft = existing;
    if (draft && mapReady) updateDraft(draft);
    else {
      if (marker) { map.removeLayer(marker); marker = null; }
      use.disabled = true;
    }
    retry.hidden = true;
    if (!mapReady) {
      selection.textContent = 'Cargando el mapa de OpenStreetMap…';
      window.clearTimeout(mapLoadTimer);
      mapLoadTimer = window.setTimeout(() => showMapFailure(), 10000);
    }
    window.setTimeout(() => map.invalidateSize(), 120);
    if (!existing && !destinationPoint) centerOnDestination();
    return true;
  };

  const actions = dialog.querySelector('.business-map-dialog__actions');
  const currentLocation = document.createElement('button');
  currentLocation.type = 'button';
  currentLocation.className = 'text-link';
  currentLocation.textContent = 'Centrar en mi ubicación';
  currentLocation.setAttribute('aria-label', 'Centrar el mapa con GPS o ubicación aproximada por IP');
  actions?.prepend(currentLocation);
  const locationNote = document.createElement('p');
  locationNote.className = 'map-location-note';
  locationNote.textContent = 'El navegador pedirá permiso para usar GPS o ubicación de red. Si falla, se consultará una ubicación aproximada por IP a un servicio externo. Esto solo centra el mapa; debes marcar la ubicación exacta del negocio.';
  actions?.before(locationNote);

  const centerOnPoint = (point, zoom, message) => {
    if (!map) return;
    destinationCenterPending = false;
    setMapCenter(point, zoom);
    draft = null;
    if (marker) { map.removeLayer(marker); marker = null; }
    use.disabled = true;
    selection.textContent = message;
  };

  const centerByIp = async () => {
    selection.textContent = 'Buscando una ubicación aproximada por IP…';
    const response = await fetch('https://ipwho.is/', {
      headers: { Accept: 'application/json' },
      signal: AbortSignal.timeout(7000),
    });
    if (!response.ok) throw new Error('El servicio de ubicación aproximada no respondió.');
    const result = await response.json();
    const point = [Number(result.latitude), Number(result.longitude)];
    if (!result.success || !point.every(Number.isFinite)) throw new Error('No se pudo estimar la ubicación por IP.');
    const place = [result.city, result.region].filter(Boolean).join(', ');
    centerOnPoint(point, 12, `Mapa centrado aproximadamente por IP${place ? ` en ${place}` : ''}. Marca el punto exacto del negocio.`);
  };

  currentLocation.addEventListener('click', () => {
    currentLocation.disabled = true;
    const fallback = () => centerByIp().catch((error) => { selection.textContent = `${error.message} Busca el destino manualmente en el mapa.`; }).finally(() => { currentLocation.disabled = false; });
    if (!navigator.geolocation) { fallback(); return; }
    selection.textContent = 'Solicitando ubicación al navegador…';
    navigator.geolocation.getCurrentPosition(
      (position) => {
        const { latitude, longitude, accuracy } = position.coords;
        const distance = Math.max(0, Math.round(accuracy));
        centerOnPoint([latitude, longitude], 16, `Mapa centrado con la ubicación del dispositivo (precisión aproximada: ${distance} m). Esto no marca el negocio; selecciona su punto exacto.`);
        currentLocation.disabled = false;
      },
      fallback,
      { enableHighAccuracy: true, timeout: 8000, maximumAge: 300000 },
    );
  });

  open.addEventListener('click', () => {
    if (!destination.value) { status.textContent = 'Selecciona primero el municipio o destino.'; destination.focus(); return; }
    dialog.showModal();
    ensureMap();
  });
  close.addEventListener('click', () => dialog.close());
  cancel.addEventListener('click', () => dialog.close());
  retry?.addEventListener('click', () => {
    retry.hidden = true;
    selection.textContent = 'Reintentando cargar OpenStreetMap…';
    mapReady = false;
    window.clearTimeout(mapLoadTimer);
    mapLayer?.redraw();
    map?.invalidateSize();
    mapLoadTimer = window.setTimeout(() => showMapFailure(), 10000);
  });
  dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });

  destination.addEventListener('change', () => {
    latInput.value = '';
    lonInput.value = '';
    mapUrl.value = '';
    mapChanged.value = '1';
    confirmed.checked = false;
    status.textContent = 'El destino cambió. Elige y confirma de nuevo el punto del negocio.';
    if (map) {
      draft = null;
      destinationCenterPending = false;
      if (marker) { map.removeLayer(marker); marker = null; }
      use.disabled = true;
      centerOnDestination();
    }
  });
  [address, reference].filter(Boolean).forEach((input) => input.addEventListener('input', () => {
    confirmed.checked = false;
    mapChanged.value = '1';
  }));

  use.addEventListener('click', async () => {
    if (!draft) return;
    const [lat, lon] = draft;
    latInput.value = lat.toFixed(7);
    lonInput.value = lon.toFixed(7);
    mapUrl.value = `https://www.openstreetmap.org/?mlat=${encodeURIComponent(lat.toFixed(7))}&mlon=${encodeURIComponent(lon.toFixed(7))}#map=18/${encodeURIComponent(lat.toFixed(7))}/${encodeURIComponent(lon.toFixed(7))}`;
    mapChanged.value = '1';
    confirmed.checked = false;
    dialog.close();
    status.textContent = `Punto guardado: ${lat.toFixed(6)}, ${lon.toFixed(6)}. Revisa la dirección y confirma que corresponde al negocio.`;
    if (Date.now() - lastReverseLookup < 1100) {
      status.textContent = 'Punto guardado. Escribe la dirección o una referencia y confirma el punto.';
      return;
    }
    lastReverseLookup = Date.now();
    try {
      status.textContent = 'Punto guardado. Consultando una dirección aproximada para ayudarte a revisar…';
      const query = new URLSearchParams({ format: 'jsonv2', lat: String(lat), lon: String(lon), zoom: '18', addressdetails: '1', 'accept-language': 'es' });
      const response = await fetch(`https://nominatim.openstreetmap.org/reverse?${query}`, { headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error('reverse lookup unavailable');
      const result = await response.json();
      const label = (result.address?.road || result.address?.pedestrian || result.address?.footway || result.address?.path || result.address?.neighbourhood || result.address?.suburb || result.display_name || '').trim();
      if (label) {
        if (!address.value.trim()) address.value = label.slice(0, 300);
        status.textContent = `Punto guardado. Dirección aproximada de OpenStreetMap: ${label}. Revísala y confirma que corresponde al negocio.`;
      } else status.textContent = 'Punto guardado. No se encontró una dirección aproximada; escribe la dirección o una referencia y confirma el punto.';
    } catch {
      status.textContent = 'Punto guardado. No se pudo sugerir la dirección automáticamente; complétala manualmente y confirma el punto.';
    }
  });
})();
