(() => {
  const image = document.getElementById('hero-place-image');
  if (!image) return;
  const english = document.documentElement.lang === 'en';
  const descriptions = english ? {
    town: 'Conceptual illustration of a mountain town with accommodation, restaurants, shops and outdoor activities',
    city: 'Conceptual illustration of a modern city with office towers, hotels, highways, gourmet restaurants, rooftop bars and nightlife'
  } : {
    town: 'Ilustración conceptual de un pueblo entre montañas con alojamiento, restaurantes, comercios y actividades al aire libre',
    city: 'Ilustración conceptual de una ciudad moderna con torres de oficinas, hoteles, autopistas, restaurantes gourmet, bares en terrazas y vida nocturna'
  };
  let current = 'town';
  let requested = 'town';
  for (const button of document.querySelectorAll('[data-place]')) {
    button.addEventListener('click', () => {
      const place = button.dataset.place;
      if (place === requested) return;
      requested = place;
      if (place === current) return;
      const url = place === 'city' ? image.dataset.cityImage : image.dataset.townImage;
      const pending = new Image();
      pending.onload = () => {
        if (requested !== place) return;
        image.src = url; image.alt = descriptions[place]; current = place;
        for (const item of document.querySelectorAll('[data-place]')) item.setAttribute('aria-pressed', String(item.dataset.place === place));
      };
      pending.onerror = () => { if (requested === place) requested = current; image.alt = descriptions[current]; };
      pending.src = url;
    });
  }
})();
