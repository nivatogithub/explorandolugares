/* Local review only: rejected references must not appear as approved scenes. */
(() => {
  if (!/\/destinos\/zipaquira\/?$/.test(location.pathname)) return;
  const pending = ['colegio-gabo', 'museo-quevedo'];
  function apply() {
    for (const slug of pending) {
      document.querySelectorAll(`[data-place-slug="${slug}"], [data-experience-slug="${slug}"]`).forEach(card => {
        card.dataset.visualStatus = 'pending-reference';
        card.querySelectorAll('.highlight-photo, img').forEach(media => media.remove());
      });
    }
    document.querySelectorAll('[data-place-slug="catedral-de-sal"] img').forEach(img => {
      img.style.setProperty('object-fit', 'contain', 'important');
      img.style.background = '#f1f2f4';
    });
    const footer = document.querySelector('footer');
    if (footer && !document.getElementById('zipaquira-image-credits')) {
      const link = document.createElement('a');
      link.id = 'zipaquira-image-credits';
      link.href = '/assets/zipaquira-revision/creditos.html';
      link.textContent = document.documentElement.lang.startsWith('en') ? 'Illustration credits' : document.documentElement.lang.startsWith('pt') ? 'Créditos das ilustrações' : 'Créditos de las ilustraciones';
      link.style.cssText = 'display:block;font-size:.8rem;margin-top:.5rem';
      footer.append(link);
    }
  }
  apply();
  document.addEventListener('DOMContentLoaded', apply);
  window.addEventListener('load', apply);
})();
