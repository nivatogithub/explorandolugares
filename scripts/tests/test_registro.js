const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const script = fs.readFileSync('ensayos/v1/assets/registro.js', 'utf8');
async function scenario(mode, invalidCase, lang='es') {
  const nodes = {
    'registration-fields': {disabled: false},
    'registration-availability': {}, 'registration-result': {focus() {}},
    'registration-submit': {disabled: true}, 'privacy-summary': {}
  };
  let invalidMarked = false;
  const invalidField = {setAttribute(name, value) { if (name === 'aria-invalid' && value === 'true') invalidMarked = true; }};
  const form = {action: '/api/participacion.php', elements: {lang: {}, csrf: {}, request_key: {}, email: {value: invalidCase === 'missing-contact' ? '' : 'test@example.invalid', focus() {}}, phone_number: {value: ''}, consent: {checked: invalidCase !== 'consent', focus() {}}, understanding: {checked: invalidCase !== 'understanding', focus() {}}}, reportValidity() {return invalidCase !== 'invalid-format'}, querySelectorAll(selector) {return selector === ':invalid' && invalidCase === 'invalid-format' ? [invalidField] : []}, addEventListener(name, handler) {this.submit = handler;}};
  nodes['business-registration'] = form;
  let posts = 0;
  const context = {FormData: class {}, document: {documentElement: {lang}, getElementById: id => nodes[id]}, window: {}, fetch: async (url, options) => {
    if (options.method === 'POST') posts++;
    if (mode === 'offline') throw Error('No backend');
    return {ok: true, json: async () => ({enabled: mode === 'enabled', message: 'No disponible', csrf: 'token', request_key: 'id', privacy: {operator: 'Prueba', contact: 'test@example.invalid', retention: 'Test'}})};
  }};
  vm.runInNewContext(script, context);
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(nodes['registration-fields'].disabled, false, 'La selección permanece disponible');
  assert.equal(nodes['registration-submit'].disabled, mode !== 'enabled');
  if (mode === 'enabled' && invalidCase) {
    await form.submit({preventDefault() {}});
    assert.equal(posts, 0, 'Consentimiento, contacto y formato requeridos');
    if (invalidCase === 'invalid-format') assert.equal(invalidMarked, true, 'Los campos obligatorios inválidos quedan marcados visualmente');
  }
  if (mode !== 'enabled') {
    nodes['registration-submit'].disabled = false;
    await form.submit({preventDefault() {}});
    assert.equal(posts, 0, 'No se envía sin disponibilidad confirmada');
  }
}
(async () => {
  for (const mode of ['offline', 'disabled', 'enabled']) await scenario(mode);
  await scenario('offline', undefined, 'pt-BR');
  for (const invalidCase of ['missing-contact', 'consent', 'understanding', 'invalid-format']) await scenario('enabled', invalidCase);
  console.log('OK: campos disponibles ES/EN/PT-BR y envío protegido con servidor ausente, deshabilitado o habilitado.');
})().catch(error => {console.error(error);process.exitCode = 1;});
