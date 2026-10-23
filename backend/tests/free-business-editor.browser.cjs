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
    await page.route('https://tile.openstreetmap.org/**', route=>route.fulfill({contentType:'image/png',body:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=','base64')}));
    await page.route('https://nominatim.openstreetmap.org/**', route=>route.fulfill({json:route.request().url().includes('/reverse?')
      ? {address:{road:'Calle de prueba',town:'Jericó'}}
      : [{lat:'5.791',lon:'-75.786',boundingbox:['5.70','5.88','-75.88','-75.69'],geojson:{type:'Polygon',coordinates:[[[-75.88,5.70],[-75.69,5.70],[-75.69,5.88],[-75.88,5.88],[-75.88,5.70]]]}}]}));
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
    await page.locator('#business-map-open').click();
    const map=page.locator('#business-location-map');
    const selection=page.locator('#business-map-selection');
    await page.waitForFunction(()=>document.querySelector('.leaflet-tile-loaded') && !document.querySelector('#business-map-selection').textContent.includes('Buscando'),null,{timeout:10000}).catch(async error=>{throw new Error(error.message+'; map status: '+await selection.textContent()+'; errors: '+errors.join('; '));});
    await map.click({position:{x:250,y:180}});
    await page.waitForFunction(()=>document.querySelector('#business-map-selection').textContent.includes('Punto seleccionado:'),null,{timeout:3000}).catch(async error=>{throw new Error(error.message+'; selection: '+await selection.textContent()+'; errors: '+errors.join('; '));});
    assert(await page.locator('#business-map-use').isEnabled());
    await page.locator('#business-map-pan-mode').click();
    await map.click({position:{x:300,y:200}});
    assert.match(await selection.textContent(),/Punto seleccionado:/,'Pan mode must also allow a click to select.');
    for(let i=0;i<12;i++) {
      const zoomOut=page.locator('.leaflet-control-zoom-out');
      if(await zoomOut.getAttribute('aria-disabled')==='true') break;
      await zoomOut.click();
      await page.waitForFunction(()=>!document.querySelector('.leaflet-zoom-anim'));
    }
    assert.equal(await page.locator('.leaflet-control-zoom-out').getAttribute('aria-disabled'),'true','Zoom out must stop at the destination scale.');
    await page.getByRole('button',{name:'Volver a centrar el mapa en el destino'}).click();
    await page.waitForFunction(()=>!document.querySelector('#business-map-selection').textContent.includes('Buscando'));
    await map.click({position:{x:300,y:200}});
    assert.match(await selection.textContent(),/Punto seleccionado:/,'Recentering with cached tiles must not block selection.');
    await page.locator('#business-map-use').click();
    await page.waitForFunction(()=>!document.querySelector('#business-map-dialog').open);
    assert.equal(await page.locator('#business-location-confirmed').inputValue(),'1');
    assert.match(await page.locator('#business-map-url').inputValue(),/^https:\/\/www\.google\.com\/maps\/search\/\?api=1&query=/);
    // Discard the map draft, then exercise minimal Free saving independently.
    await page.reload();
    await plan.selectOption('free');
    await page.locator('input[name="category_codes[]"][value="alimentacion"]').check();
    assert(await address.isDisabled());
    assert.equal(await page.locator('#business-frequency').evaluate(e=>e.required),false);
    assert(await form.evaluate(e=>e.checkValidity()));
    await page.getByRole('button',{name:'Guardar negocio y continuar a su suscripción'}).click();
    await page.waitForURL('**/admin/suscripciones.php?**');
    assert.equal(new URL(page.url()).searchParams.get('id'),String(input.businessId));
    assert.equal(errors.length,0,errors.join('\n'));
    console.log('OK: map selection works in both modes, zoom out is bounded, recentering preserves selection, and coordinates are confirmed; minimal Free editing saves the existing ID without location or contact.');
  } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
