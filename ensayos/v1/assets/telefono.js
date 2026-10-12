(() => {
  const form = document.getElementById('business-registration');
  if (!form) return;
  const type = form.elements.phone_type;
  const number = form.elements.phone_number;
  const prefix = document.getElementById('phone-prefix');
  const help = document.getElementById('phone-help');
  const english = document.documentElement.lang === 'en';
  function update() {
    const fixed = type.value === 'fijo';
    prefix.textContent = fixed ? '+57 604' : '+57';
    number.maxLength = fixed ? 7 : 10;
    number.pattern = fixed ? '[0-9]{7}' : '3[0-9]{9}';
    help.textContent = fixed
      ? (english ? 'Enter the 7-digit landline number. Antioquia’s +57 604 code is added automatically.' : 'Escribe los 7 dígitos del fijo. El indicativo +57 604 de Antioquia se añade automáticamente.')
      : (english ? 'Enter the 10-digit mobile number, starting with 3. Do not include +57.' : 'Escribe los 10 dígitos del celular, empezando por 3. No incluyas +57.');
    number.setCustomValidity('');
  }
  number.addEventListener('input', () => number.setCustomValidity(''));
  number.addEventListener('invalid', () => {
    if (number.validity.patternMismatch || number.validity.tooLong) {
      number.setCustomValidity(type.value === 'fijo'
        ? (english ? 'Enter the 7-digit landline number without the dialling code.' : 'Escribe los 7 dígitos del teléfono fijo, sin indicativo.')
        : (english ? 'Enter the 10-digit mobile number, starting with 3.' : 'Escribe los 10 dígitos del celular, empezando por 3.'));
    }
  });
  form.querySelectorAll('input[name=phone_type]').forEach(option => option.addEventListener('change', update));
  update();
})();
