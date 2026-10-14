'use strict';
document.querySelectorAll('.request-status-filter').forEach(function (form) {
  form.addEventListener('change', function (event) {
    if (event.target.matches('input[type=radio][name=status]')) form.requestSubmit();
  });
});
