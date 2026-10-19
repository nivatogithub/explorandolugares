(() => {
  const form = document.getElementById('business-registration');
  if (!form) return;
  const type = form.elements.phone_type;
  const number = form.elements.phone_number;
  const municipality=form.elements.municipality;
  const prefix = document.getElementById('phone-prefix');
  const help = document.getElementById('phone-help');
  const english = document.documentElement.lang === 'en';
  const portuguese = document.documentElement.lang === 'pt-BR';
  const text = (spanish, englishText, portugueseText) => english ? englishText : (portuguese ? portugueseText : spanish);
  function update() {
    const fixed = type.value === 'fijo';
    const area=municipality?.selectedOptions?.[0]?.dataset.landlinePrefix||'604';
    prefix.textContent = fixed ? '+57 '+area : '+57';
    number.maxLength = fixed ? 7 : 10;
    number.pattern = fixed ? '[0-9]{7}' : '3[0-9]{9}';
    help.textContent = fixed
      ? text('Escribe los 7 dígitos del fijo. El indicativo '+prefix.textContent+' del municipio seleccionado se añade automáticamente.', 'Enter the 7-digit landline number. The selected municipality’s '+prefix.textContent+' code is added automatically.', 'Digite os 7 dígitos do telefone fixo. O código '+prefix.textContent+' do município selecionado é adicionado automaticamente.')
      : text('Escribe los 10 dígitos del celular, empezando por 3. No incluyas +57.', 'Enter the 10-digit mobile number, starting with 3. Do not include +57.', 'Digite os 10 dígitos do celular, começando por 3. Não inclua +57.');
    number.setCustomValidity('');
  }
  number.addEventListener('input', () => number.setCustomValidity(''));
  number.addEventListener('invalid', () => {
    if (number.validity.patternMismatch || number.validity.tooLong) {
      number.setCustomValidity(type.value === 'fijo'
        ? text('Escribe los 7 dígitos del teléfono fijo, sin indicativo.', 'Enter the 7-digit landline number without the dialling code.', 'Digite os 7 dígitos do telefone fixo, sem o código de área.')
        : text('Escribe los 10 dígitos del celular, empezando por 3.', 'Enter the 10-digit mobile number, starting with 3.', 'Digite os 10 dígitos do celular, começando por 3.'));
    }
  });
  form.querySelectorAll('input[name=phone_type]').forEach(option => option.addEventListener('change', update));
  municipality?.addEventListener('change',update);
  update();
})();
