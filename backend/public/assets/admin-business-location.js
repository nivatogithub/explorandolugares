(() => {
  const $ = (id) => document.getElementById(id);
  const address = $('business-address');
  const reference = $('business-location-reference');
  const destination = $('business-destination');
  const open = $('business-map-open');
  const dialog = $('business-map-dialog');
  const close = $('business-map-close');
  const cancel = $('business-map-cancel');
  const use = $('business-map-use');
  const panMode = $('business-map-pan-mode');
  const markMode = $('business-map-mark-mode');
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
  let pendingAddressPoint = null;
  let addressLookupPoint = null;
  let mapMode = 'pan';
  let destinationBounds = null;
  let destinationGeometry = null;
  let geolocationRequest = 0;

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

  const normalizePlace = (value) => String(value || '')
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es')
    .replace(/^(municipio de|municipality of|municipio)\s+/u, '')
    .replace(/[^a-z0-9]+/g, ' ').trim();

  const pointWithinBounds = (point) => {
    if (!destinationBounds) return null;
    const [lat, lon] = point;
    return lat >= destinationBounds.south && lat <= destinationBounds.north
      && lon >= destinationBounds.west && lon <= destinationBounds.east;
  };

  const pointInRing = (point, ring) => {
    const [lat, lon] = point;
    let inside = false;
    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
      const [xi, yi] = ring[i];
      const [xj, yj] = ring[j];
      const crosses = (yi > lat) !== (yj > lat)
        && lon < ((xj - xi) * (lat - yi)) / ((yj - yi) || Number.EPSILON) + xi;
      if (crosses) inside = !inside;
    }
    return inside;
  };

  const pointInGeometry = (point, geometry) => {
    if (!geometry?.coordinates) return null;
    const polygons = geometry.type === 'Polygon' ? [geometry.coordinates]
      : geometry.type === 'MultiPolygon' ? geometry.coordinates : [];
    for (const polygon of polygons) {
      const [outer, ...holes] = polygon;
      if (outer && pointInRing(point, outer) && !holes.some((ring) => pointInRing(point, ring))) return true;
    }
    return polygons.length ? false : null;
  };

  const setMapMode = (mode) => {
    mapMode = mode === 'mark' ? 'mark' : 'pan';
    panMode?.setAttribute('aria-pressed', String(mapMode === 'pan'));
    markMode?.setAttribute('aria-pressed', String(mapMode === 'mark'));
    if (map?.dragging) {
      if (mapMode === 'pan') map.dragging.enable();
      else map.dragging.disable();
    }
    if (marker?.dragging) {
      if (mapMode === 'mark') marker.dragging.enable();
      else marker.dragging.disable();
    }
    if (mapReady) selection.textContent = mapMode === 'pan'
      ? 'Modo mover mapa: arrastra para explorar. Cambia a “Marcar punto” para ubicar el negocio.'
      : 'Modo marcar punto: toca o haz clic en la ubicación exacta del negocio.';
  };

  const geocodeAddress = async () => {
    const text = [address.value.trim(), selectedDestinationName(), 'Colombia'].filter(Boolean).join(', ');
    if (!address.value.trim()) return null;
    const params = new URLSearchParams({ q: text, countrycodes: 'co', format: 'jsonv2', limit: '1', addressdetails: '1' });
    const response = await fetch(`https://nominatim.openstreetmap.org/search?${params}`, {
      headers: { Accept: 'application/json' },
      signal: AbortSignal.timeout(7000),
    });
    if (!response.ok) throw new Error('No se pudo ubicar la dirección escrita.');
    const results = await response.json();
    const result = results[0];
    if (!result) return null;
    const point = [Number(result.lat), Number(result.lon)];
    if (!point.every(Number.isFinite)) return null;
    return { point, label: result.display_name || '' };
  };

  const distanceMeters = (a, b) => {
    const rad = (value) => value * Math.PI / 180;
    const dLat = rad(b[0] - a[0]);
    const dLon = rad(b[1] - a[1]);
    const value = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a[0])) * Math.cos(rad(b[0])) * Math.sin(dLon / 2) ** 2;
    return 6371000 * 2 * Math.atan2(Math.sqrt(value), Math.sqrt(1 - value));
  };

  const reverseAddress = async (point) => {
    const params = new URLSearchParams({ format: 'jsonv2', lat: String(point[0]), lon: String(point[1]), zoom: '18', addressdetails: '1', 'accept-language': 'es' });
    const response = await fetch(`https://nominatim.openstreetmap.org/reverse?${params}`, {
      headers: { Accept: 'application/json' },
      signal: AbortSignal.timeout(7000),
    });
    if (!response.ok) throw new Error('No se pudo consultar la dirección de este punto.');
    const result = await response.json();
    const parts = result.address || {};
    const street = [parts.house_number, parts.road || parts.pedestrian || parts.footway || parts.path].filter(Boolean).join(' ');
    return {
      label: street || parts.neighbourhood || parts.suburb || parts.village || parts.town || parts.city || '',
      localities: [parts.municipality, parts.town, parts.city, parts.village].filter(Boolean),
    };
  };

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
    const name = selectedDestinationName();
    if (!name) return null;

    const cacheKey = `el-destination-center:${name.toLocaleLowerCase('es')}:co`;
    try {
      const cached = JSON.parse(sessionStorage.getItem(cacheKey) || 'null');
      if (cached?.point?.length === 2 && cached.point.every(Number.isFinite)) {
        destinationBounds = cached.bounds || null;
        destinationGeometry = cached.geometry || null;
        return cached;
      }
    } catch { /* Storage can be disabled; geocoding can still continue. */ }

    const params = new URLSearchParams({
      city: name,
      state: 'Antioquia',
      country: 'Colombia',
      countrycodes: 'co',
      format: 'jsonv2',
      addressdetails: '1',
      polygon_geojson: '1',
      limit: '1',
    });
    const response = await fetch(`https://nominatim.openstreetmap.org/search?${params}`, {
      headers: { Accept: 'application/json' },
      signal: AbortSignal.timeout(7000),
    });
    if (!response.ok) throw new Error('No se pudo encontrar el destino.');
    const results = await response.json();
    const result = results[0];
    const knownPoint = selectedDestination();
    const point = knownPoint || (result ? [Number(result.lat), Number(result.lon)] : null);
    if (!point || !point.every(Number.isFinite)) throw new Error('No se encontraron coordenadas para este destino.');
    const rawBounds = Array.isArray(result?.boundingbox) ? result.boundingbox.map(Number) : null;
    const bounds = rawBounds?.length === 4 && rawBounds.every(Number.isFinite)
      ? { south: rawBounds[0] - 0.015, north: rawBounds[1] + 0.015, west: rawBounds[2] - 0.015, east: rawBounds[3] + 0.015 }
      : null;
    destinationBounds = bounds;
    destinationGeometry = result?.geojson?.type && Array.isArray(result.geojson.coordinates) ? result.geojson : null;
    const resolved = { point, bounds, geometry: destinationGeometry };
    try { sessionStorage.setItem(cacheKey, JSON.stringify(resolved)); } catch { /* Optional cache. */ }
    return resolved;
  };

  const applyDestinationBounds = (bounds) => {
    destinationBounds = bounds || null;
    if (!map) return;
    if (!bounds || !window.L?.latLngBounds) {
      map.setMaxBounds(null);
      return;
    }
    map.setMaxBounds(L.latLngBounds([bounds.south, bounds.west], [bounds.north, bounds.east]));
    map.options.maxBoundsViscosity = 0.9;
  };

  const reverseMatchesSelectedDestination = (result) => {
    const expected = normalizePlace(selectedDestinationName());
    const actuals = (result?.localities || []).map(normalizePlace).filter(Boolean);
    if (expected && actuals.length) {
      return actuals.some((actual) => actual === expected || actual.includes(expected) || expected.includes(actual));
    }
    return null;
  };

  const isInSelectedDestination = async (point, resolvedAddress = null) => {
    const geometryResult = pointInGeometry(point, destinationGeometry);
    if (geometryResult !== null) return geometryResult;
    const withinGeocoderBounds = pointWithinBounds(point);
    if (withinGeocoderBounds === false) return false;
    if (resolvedAddress) return reverseMatchesSelectedDestination(resolvedAddress) ?? withinGeocoderBounds;
    try {
      const result = await reverseAddress(point);
      return reverseMatchesSelectedDestination(result) ?? withinGeocoderBounds;
    } catch {
      return withinGeocoderBounds;
    }
  };

  const centerOnDestination = async (shouldCenter = true) => {
    if (!map) return;
    const name = selectedDestinationName();
    if (!name) return;
    destinationCenterPending = true;
    selection.textContent = `Buscando el centro de ${name} para mostrar el municipio correcto…`;
    try {
      const result = await geocodeDestination();
      if (!result?.point || !map) throw new Error('No se encontraron coordenadas para este destino.');
      applyDestinationBounds(result.bounds);
      destinationCenterPending = false;
      if (shouldCenter) setMapCenter(result.point, 16);
      selection.textContent = shouldCenter
        ? `Mapa centrado en ${name}. El pin se conserva; usa “Marcar punto” para seleccionar o cambiar su ubicación.`
        : `Área de ${name} cargada. El pin existente se conserva.`;
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
    if (pointWithinBounds(point) === false || pointInGeometry(point, destinationGeometry) === false) {
      selection.textContent = `Ese punto queda fuera del área aproximada de ${selectedDestinationName()}. Vuelve al destino y elige un punto dentro de él.`;
      return;
    }
    draft = point;
    const [lat, lon] = point;
    selection.textContent = `Punto seleccionado: ${lat.toFixed(6)}, ${lon.toFixed(6)}.`;
    use.disabled = false;
    if (marker) marker.setLatLng(point);
    else if (map) marker = L.marker(point, { draggable: mapMode === 'mark' }).addTo(map).on('dragend', (event) => updateDraft(event.target.getLatLng().wrap().toArray()));
    if (marker?.dragging) {
      if (mapMode === 'mark') marker.dragging.enable();
      else marker.dragging.disable();
    }
  };

  const showMapFailure = () => {
    mapReady = false;
    draft = null;
    use.disabled = true;
    selection.textContent = 'No se pudieron cargar los mapas de OpenStreetMap. Revisa la conexión; al volver a abrir esta ventana se intentará cargarlos de nuevo.';
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
      map = L.map(mapNode, { scrollWheelZoom: true, maxBoundsViscosity: 0.9 }).setView(initialCenter, existing ? 17 : destinationPoint ? 16 : 5);
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
        if (pendingAddressPoint) { const point = pendingAddressPoint; pendingAddressPoint = null; updateDraft(point); }
        else if (draft) updateDraft(draft);
        else if (!destinationCenterPending) selection.textContent = mapMode === 'pan'
          ? 'Modo mover mapa: arrastra para explorar. Cambia a “Marcar punto” para ubicar el negocio.'
          : 'Modo marcar punto: toca o haz clic en la ubicación exacta del negocio.';
      });
      mapLayer.on('load', () => { if (!mapReady) showMapFailure(); });
      mapLayer.addTo(map);
      map.on('click', (event) => { if (mapMode === 'mark') updateDraft(event.latlng.wrap().toArray()); });
    } else {
      setMapCenter(initialCenter, existing ? 17 : destinationPoint ? 16 : 5);
      if (!mapReady) mapLayer?.redraw();
    }
    draft = existing;
    if (draft && mapReady) updateDraft(draft);
    else {
      if (marker) { map.removeLayer(marker); marker = null; }
      use.disabled = true;
    }
    if (!mapReady) {
      selection.textContent = 'Cargando el mapa de OpenStreetMap…';
      window.clearTimeout(mapLoadTimer);
      mapLoadTimer = window.setTimeout(() => showMapFailure(), 10000);
    }
    window.setTimeout(() => map.invalidateSize(), 120);
    setMapMode(mapMode);
    if (!existing || !destinationBounds) centerOnDestination(!existing);
    return true;
  };

  const actions = dialog.querySelector('.business-map-dialog__actions');
  const currentLocation = document.createElement('button');
  currentLocation.type = 'button';
  currentLocation.className = 'business-map-locate-control';
  currentLocation.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="7.5"></circle><circle cx="12" cy="12" r="2"></circle><path d="M12 1.8v3M12 19.2v3M1.8 12h3M19.2 12h3"></path></svg>';
  currentLocation.setAttribute('aria-label', 'Centrar el mapa con GPS o ubicación aproximada por IP');
  mapNode.append(currentLocation);
  currentLocation.title = 'Centrar en mi ubicación';

  const returnToDestination = document.createElement('button');
  returnToDestination.type = 'button';
  returnToDestination.className = 'business-map-target-control';
  returnToDestination.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-6.1 7-12a7 7 0 1 0-14 0c0 5.9 7 12 7 12Z"></path><circle cx="12" cy="9" r="2.5"></circle></svg>';
  returnToDestination.setAttribute('aria-label', 'Volver a centrar el mapa en el destino');
  returnToDestination.title = 'Volver al destino';
  mapNode.append(returnToDestination);

  const centerOnPoint = (point, zoom, message) => {
    if (!map) return;
    destinationCenterPending = false;
    setMapCenter(point, zoom);
    selection.textContent = message;
  };

  const centerOnlyIfInDestination = async (point, zoom, source, message) => {
    const requestId = ++geolocationRequest;
    selection.textContent = `Comprobando si la ubicación ${source} está dentro de ${selectedDestinationName()}…`;
    const inside = await isInSelectedDestination(point);
    if (requestId !== geolocationRequest) return;
    if (!inside) {
      const reason = inside === false
        ? `La ubicación ${source} está fuera de ${selectedDestinationName()}.`
        : `No se pudo confirmar que la ubicación ${source} esté dentro de ${selectedDestinationName()}.`;
      selection.textContent = `${reason} El mapa se queda en el destino. Usa “Volver al destino” si te desplazaste.`;
      return;
    }
    centerOnPoint(point, zoom, `${message} El marcador seleccionado se conserva.`);
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
    await centerOnlyIfInDestination(point, 14, 'estimada por IP', `Mapa centrado aproximadamente por IP${place ? ` en ${place}` : ''}.`);
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
        centerOnlyIfInDestination([latitude, longitude], 16, 'del dispositivo', `Mapa centrado con la ubicación del dispositivo (precisión aproximada: ${distance} m).`)
          .finally(() => { currentLocation.disabled = false; });
      },
      fallback,
      { enableHighAccuracy: true, timeout: 8000, maximumAge: 300000 },
    );
  });

  returnToDestination.addEventListener('click', () => centerOnDestination());
  panMode?.addEventListener('click', () => setMapMode('pan'));
  markMode?.addEventListener('click', () => setMapMode('mark'));

  open.addEventListener('click', () => {
    if (!destination.value) { status.textContent = 'Selecciona primero el destino.'; destination.focus(); return; }
    dialog.showModal();
    ensureMap();
    if (!parseExisting() && address.value.trim()) {
      selection.textContent = 'Buscando la dirección para centrar el mapa…';
      geocodeAddress().then(async (result) => {
        if (!result || !map) return;
        if (pointWithinBounds(result.point) === false || await isInSelectedDestination(result.point) === false) {
          selection.textContent = `La dirección parece estar fuera de ${selectedDestinationName()}. El mapa permanece en el destino; revisa la dirección.`;
          return;
        }
        addressLookupPoint = result.point;
        pendingAddressPoint = result.point;
        destinationCenterPending = false;
        setMapCenter(result.point, 18);
        if (mapReady) { pendingAddressPoint = null; updateDraft(result.point); }
      }).catch(() => { selection.textContent = 'No se ubicó la dirección automáticamente. Puedes encontrarla en el mapa y marcar el punto.'; });
    }
  });
  close.addEventListener('click', () => dialog.close());
  cancel.addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });

  destination.addEventListener('change', () => {
    latInput.value = '';
    lonInput.value = '';
    mapUrl.value = '';
    mapChanged.value = '1';
    confirmed.value = '0';
    status.textContent = 'El destino cambió. Elige y confirma de nuevo el punto del negocio.';
    if (map) {
      draft = null;
      destinationCenterPending = false;
      if (marker) { map.removeLayer(marker); marker = null; }
      use.disabled = true;
      destinationBounds = null;
      destinationGeometry = null;
      centerOnDestination();
    }
  });
  address.addEventListener('input', () => {
    confirmed.value = '0';
    mapChanged.value = '1';
    latInput.value = ''; lonInput.value = ''; mapUrl.value = ''; addressLookupPoint = null;
  });

  use.addEventListener('click', async () => {
    if (!draft) return;
    const [lat, lon] = draft;
    const selectedPoint = [lat, lon];
    if (addressLookupPoint && distanceMeters(addressLookupPoint, selectedPoint) > 1000) {
      selection.textContent = 'El punto está a más de 1 km de la ubicación que encontró el sistema para la dirección. Revisa la dirección o selecciona el punto correcto.';
      return;
    }
    if (Date.now() - lastReverseLookup < 1100) {
      selection.textContent = 'Espera un momento antes de confirmar otro punto para no repetir consultas al mapa.';
      return;
    }
    lastReverseLookup = Date.now();
    use.disabled = true;
    selection.textContent = 'Comprobando el punto y consultando su dirección…';
    let resolvedAddress;
    try {
      resolvedAddress = await reverseAddress(selectedPoint);
    } catch {
      selection.textContent = 'No se pudo verificar el punto con el mapa. Comprueba tu conexión e inténtalo de nuevo.';
      use.disabled = false;
      return;
    }
    const inside = await isInSelectedDestination(selectedPoint, resolvedAddress);
    if (inside !== true) {
      selection.textContent = inside === false
        ? `La ubicación marcada está fuera de ${selectedDestinationName()}. Elige un punto dentro del destino.`
        : `No se pudo confirmar que el punto esté dentro de ${selectedDestinationName()}. Verifica la conexión y vuelve a intentarlo.`;
      use.disabled = false;
      return;
    }
    latInput.value = lat.toFixed(7);
    lonInput.value = lon.toFixed(7);
    mapUrl.value = `https://www.openstreetmap.org/?mlat=${encodeURIComponent(lat.toFixed(7))}&mlon=${encodeURIComponent(lon.toFixed(7))}#map=18/${encodeURIComponent(lat.toFixed(7))}/${encodeURIComponent(lon.toFixed(7))}`;
    mapChanged.value = '1';
    confirmed.value = '0';
    const saveButton = document.querySelector('#business-form button[type="submit"]');
    if (saveButton) saveButton.disabled = true;
    dialog.close();
    if (resolvedAddress.label) {
        address.value = resolvedAddress.label.slice(0, 300);
        confirmed.value = '1';
        addressLookupPoint = selectedPoint;
        status.textContent = `Ubicación lista. Dirección sugerida por el mapa: ${resolvedAddress.label}. El destino y estas coordenadas quedarán guardados juntos.`;
      } else {
        confirmed.value = address.value.trim() ? '1' : '0';
        addressLookupPoint = selectedPoint;
        status.textContent = address.value.trim() ? 'Ubicación lista. El mapa no encontró nomenclatura en este punto; se guardarán juntos la dirección escrita y el pin seleccionado.' : 'El mapa no encontró una dirección aquí. Escribe una referencia reconocible para poder guardar la ubicación.';
      }
    if (saveButton) saveButton.disabled = confirmed.value !== '1';
  });
})();
