const {chromium} = require('/Users/nivato/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs = require('fs');
const assert = require('assert/strict');
(async () => {
  const input = JSON.parse(fs.readFileSync(0, 'utf8'));
  assert.match(input.base, /^http:\/\/127\.0\.0\.1:\d+$/);
  const browser = await chromium.launch({headless:true,channel:'chrome'});
  try {
    const page = await browser.newPage({viewport:{width:1440,height:1000}});
    const errors=[];
    page.on('pageerror', e=>errors.push(e.message));
    await page.route('https://**', route=>route.abort());
    await page.goto(input.base+'/admin/index.php');
    await page.locator('input[name=username]').fill('admin-local');
    await page.locator('input[name=password]').fill(input.password);
    await page.getByRole('button',{name:'Entrar al panel'}).click();
    await page.goto(input.base+'/admin/negocios.php?id='+input.businessId);
    const form=page.locator('#business-form');
    const plan=page.locator('#business-plan');
    const address=page.locator('#business-address');
    assert.equal(await plan.inputValue(),'free');
    assert.equal(await address.evaluate(e=>e.required),false);
    assert(await address.isDisabled());
    assert(await form.evaluate(e=>e.checkValidity()),'A free imported record must be valid with only its existing name, category and destination.');
    for(const tier of ['basic','featured','page','page_premium']) {
      await plan.selectOption(tier);
      assert.equal(await address.evaluate(e=>e.required),true,tier+' requires address');
      assert(await address.isEnabled());
      assert(await page.getByRole('heading',{name:'Editar negocio'}).isVisible());
      assert(await page.locator('input[name="contacts[phone][value]"]').isEnabled());
      assert.equal(await page.locator('[name="translations[es][summary]"]').evaluate(e=>e.required),true);
      assert.equal(await form.evaluate(e=>e.checkValidity()),false,'Paid tier must not silently pass with missing required data.');
    }
    await plan.selectOption('free');
    assert(await address.isDisabled());
    assert.equal(await page.locator('#business-frequency').evaluate(e=>e.required),false);
    assert(await form.evaluate(e=>e.checkValidity()));
    await page.getByRole('button',{name:'Guardar negocio y continuar a su suscripción'}).click();
    await page.waitForURL('**/admin/suscripciones.php?**');
    assert.equal(new URL(page.url()).searchParams.get('id'),String(input.businessId));
    assert.equal(errors.length,0,errors.join('\n'));
    console.log('OK: browser accepts minimal Free editing; all four paid tiers restore required fields; switching back to Free saves the existing ID without location or contact.');
  } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
