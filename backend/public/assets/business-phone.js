(() => {
  'use strict';
  const areaForDepartment = (department) => ({
    Antioquia: '604', Córdoba: '604', Chocó: '604', 'Bogotá D.C.': '601', Cundinamarca: '601',
    Cauca: '602', Nariño: '602', 'Valle del Cauca': '602', Atlántico: '605', Bolívar: '605',
    Cesar: '605', 'La Guajira': '605', Magdalena: '605', Sucre: '605', Caldas: '606', Quindío: '606',
    Risaralda: '606', Santander: '607', 'Norte de Santander': '607', Arauca: '607',
  }[department] || '608');

  document.querySelectorAll('[data-business-phone]').forEach((root) => {
    if (root.dataset.ready) return;
    root.dataset.ready = '1';
    const input = (name) => root.querySelector(`[name="business_phone[${name}]"]`);
    const number = input('number');
    const whatsapp = input('whatsapp');
    const sameWhatsapp = input('same_whatsapp');
    const area = input('area');
    const destination = document.querySelector('#registration-destination');
    const digits = (value) => value.replace(/\D/g, '').replace(/^57(?=\d{10}$)/, '');
    const format = (value) => {
      const normalized = digits(value);
      return normalized.length === 10 ? `${normalized.slice(0, 3)} ${normalized.slice(3, 6)} ${normalized.slice(6)}` : normalized;
    };
    const updateArea = () => {
      const department = destination?.selectedOptions?.[0]?.dataset.department;
      if (department) area.value = areaForDepartment(department);
    };
    const update = () => {
      updateArea();
      const fixed = root.querySelector('input[value="fixed"]')?.checked;
      root.querySelector('[data-phone-help]').textContent = fixed
        ? `El indicativo ${area.value} se calcula según el lugar. Escribe los siete números del fijo.`
        : 'Ingresa los diez números del celular. El +57 se agrega automáticamente.';
      let value = digits(number.value);
      if (fixed && value.length === 7) value = area.value + value;
      root.querySelector('[data-phone-inline-area]').textContent = fixed ? area.value : '';
      root.querySelector('[data-wa-inline-area]').textContent = sameWhatsapp.checked && fixed ? area.value : '';
      if (sameWhatsapp.checked) whatsapp.value = value ? format(fixed ? value.slice(3) : value) : '';
      whatsapp.disabled = sameWhatsapp.checked;
      root.querySelector('[data-phone-preview]').textContent = value ? `Se guardará: +57 ${format(value)}` : '';
    };
    root.addEventListener('input', update);
    root.addEventListener('change', update);
    destination?.addEventListener('change', update);
    number.addEventListener('blur', () => { number.value = format(number.value); update(); });
    whatsapp.addEventListener('blur', () => { whatsapp.value = whatsapp.value ? format(whatsapp.value) : ''; update(); });
    whatsapp.value = format(whatsapp.value);
    update();
  });
})();
