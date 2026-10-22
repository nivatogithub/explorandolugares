'use strict';
document.querySelectorAll('.admin-review-status-filter input[type=radio]').forEach(input => input.addEventListener('change', () => input.form.requestSubmit()));
