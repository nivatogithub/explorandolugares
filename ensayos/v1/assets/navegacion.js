(() => {
  const navigation = document.querySelector('.destination-nav');
  if (!navigation) return;
  const entries = [...navigation.querySelectorAll('a[href^="#"]')]
    .map(link => ({link, section: document.getElementById(decodeURIComponent(link.hash.slice(1)))}))
    .filter(entry => entry.section);
  if (!entries.length) return;
  function select(active) {
    entries.forEach(entry => {
      if (entry === active) entry.link.setAttribute('aria-current', 'location');
      else entry.link.removeAttribute('aria-current');
    });
  }
  function update() {
    let current = entries[0];
    for (const entry of entries) {
      if (entry.section.getBoundingClientRect().top <= 150) current = entry;
    }
    select(current);
  }
  entries.forEach(entry => entry.link.addEventListener('click', () => select(entry)));
  let scheduled = false;
  window.addEventListener('scroll', () => {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(() => { scheduled = false; update(); });
  }, {passive: true});
  window.addEventListener('hashchange', update);
  update();
})();
