(() => {
  'use strict';
  document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-admin-account]');
    if (!trigger) return;
    const dialog = document.getElementById(trigger.dataset.adminAccount);
    if (!dialog || typeof dialog.showModal !== 'function') return;
    dialog.showModal();
    dialog.addEventListener('close', () => trigger.focus(), {once: true});
  });
})();
