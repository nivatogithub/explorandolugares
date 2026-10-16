(() => {
  const form = document.querySelector('[data-business-bulk-form]');
  if (!form) return;

  const visible = [...form.querySelectorAll('[data-business-id]:not(:disabled)')];
  const selectVisible = form.querySelector('[data-select-visible]');
  const selectFiltered = form.querySelector('[data-select-all-filtered]');

  selectVisible?.addEventListener('change', () => {
    visible.forEach((checkbox) => { checkbox.checked = selectVisible.checked; });
    if (selectFiltered) selectFiltered.checked = false;
  });
  visible.forEach((checkbox) => checkbox.addEventListener('change', () => {
    if (selectVisible) selectVisible.checked = visible.length > 0 && visible.every((item) => item.checked);
    if (selectFiltered) selectFiltered.checked = false;
  }));
  selectFiltered?.addEventListener('change', () => {
    if (selectFiltered.checked) {
      visible.forEach((checkbox) => { checkbox.checked = false; });
      if (selectVisible) selectVisible.checked = false;
    }
  });
  form.addEventListener('submit', (event) => {
    const count = selectFiltered?.checked
      ? Number(form.querySelector('.business-bulk-toolbar [data-result-count]')?.dataset.resultCount || 0)
      : visible.filter((checkbox) => checkbox.checked).length;
    if (!count) {
      event.preventDefault();
      window.alert('Selecciona al menos un negocio apto para aprobación masiva.');
      return;
    }
    if (!window.confirm(`Vas a publicar hasta ${count} negocios importados con suscripción Gratis. Los posibles duplicados o registros que no cumplan las reglas se omitirán. ¿Continuar?`)) event.preventDefault();
  });
})();
