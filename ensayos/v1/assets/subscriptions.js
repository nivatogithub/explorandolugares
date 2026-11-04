(() => {
  const select = document.querySelector('#billing-frequency');
  const title = document.querySelector('#billing-frequency-title');
  const copy = document.querySelector('#billing-frequency-copy');
  if (!select || !title || !copy) return;
  const language = document.documentElement.lang || 'es';
  const content = {
    es: {
      monthly: ['Suscripción mensual', 'Renovación mes a mes para acompañar el inicio de tu participación.'],
      quarterly: ['Suscripción trimestral', 'Un periodo de tres meses para dar continuidad a tu presencia.'],
      semiannual: ['Suscripción semestral', 'Seis meses para desarrollar tu información y mantenerla actualizada.'],
      annual: ['Suscripción anual', 'Un año para consolidar una presencia local de largo plazo.']
    },
    en: {
      monthly: ['Monthly subscription', 'Month-to-month renewal to support the start of your participation.'],
      quarterly: ['Quarterly subscription', 'A three-month period to give your presence continuity.'],
      semiannual: ['Semiannual subscription', 'Six months to develop and keep your information updated.'],
      annual: ['Annual subscription', 'One year to build a lasting local presence.']
    },
    'pt-BR': {
      monthly: ['Assinatura mensal', 'Renovação mês a mês para acompanhar o início da sua participação.'],
      quarterly: ['Assinatura trimestral', 'Um período de três meses para dar continuidade à sua presença.'],
      semiannual: ['Assinatura semestral', 'Seis meses para desenvolver e manter suas informações atualizadas.'],
      annual: ['Assinatura anual', 'Um ano para consolidar uma presença local de longo prazo.']
    }
  };
  const update = () => {
    const [heading, description] = (content[language] || content.es)[select.value] || content.es.monthly;
    title.textContent = heading;
    copy.textContent = description;
  };
  select.addEventListener('change', update);
  update();
})();
