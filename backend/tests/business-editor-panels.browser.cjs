const {chromium}=require('/Users/nivato/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const {execFileSync}=require('node:child_process');
const path=require('node:path');const assert=require('node:assert/strict');
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'});
 try{
  const page=await browser.newPage({viewport:{width:1280,height:900}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  const html=execFileSync('php',[path.join(__dirname,'business-editor-panels.fixture.php')],{encoding:'utf8'});
  await page.setContent(html.replace(/<script\b[\s\S]*?<\/script>/g,'').replace(/<link\b[^>]*>/g,''));
  await page.addStyleTag({path:path.join(__dirname,'../public/assets/cobertura/portal-tokens.css')});
  await page.addStyleTag({path:path.join(__dirname,'../public/assets/business-editor-panels.css')});
  await page.addScriptTag({path:path.join(__dirname,'../public/assets/business-editor-panels.js')});
  const products=page.locator('#editor [data-product-editor]'),tabs=products.locator(':scope > [data-editor-tabs] > button:visible');
  assert.equal(await tabs.count(),2,'Only configured products have tabs');
  assert.equal(await products.locator(':scope > [data-editor-panel]:visible').count(),1);
  await tabs.nth(1).click();assert(await products.locator('[data-product-slot="3"]').isVisible());
  const language=products.locator('[data-product-slot="3"] [data-editor-tabs] > button');
  await language.nth(1).click();assert(await page.locator('[name="showcase[products][3][translations][en][name]"]').isVisible());
  await page.locator('[name="showcase[products][3][translations][en][name]"]').fill('Changed English');
  await language.nth(0).click();await language.nth(1).click();assert.equal(await page.locator('[name="showcase[products][3][translations][en][name]"]').inputValue(),'Changed English');
  for(let i=0;i<3;i++)await products.locator(':scope > [data-add-product]').click();
  assert.equal(await tabs.count(),5);assert(await products.locator(':scope > [data-add-product]').isDisabled());
  await products.locator(':scope > [data-editor-panel]:visible [data-remove-product]').click();assert.equal(await tabs.count(),4);
  const values=await page.locator('#editor').evaluate(form=>Array.from(new FormData(form).keys()));
  assert(values.includes('showcase[products][3][translations][en][name]'),'Hidden configured product and language must submit');
  assert(values.includes('showcase[products][4][remove]'),'Removed product must submit a tombstone');
  assert(!values.includes('showcase[products][4][translations][es][name]'),'Removed controls must not submit');
  const textTabs=page.locator('#editor > fieldset > [data-editor-panels] > [data-editor-tabs] > button');
  await textTabs.nth(2).click();assert(await page.locator('[name="translations[pt-BR][display_name]"]').isVisible());
  await page.locator('[name="translations[en][display_name]"]').fill('');
  await page.locator('#editor').evaluate(form=>form.reportValidity());assert(await page.locator('[name="translations[en][display_name]"]').isVisible(),'Invalid hidden language must open');
  await page.locator('#registration #registration-new-details').evaluate(e=>{e.hidden=false;e.disabled=false;});
  const registration=page.locator('#registration [data-product-editor]');await registration.evaluate(e=>{e.hidden=false;});
  assert.equal(await registration.locator(':scope > [data-editor-tabs] > button:visible').count(),0);
  await registration.locator(':scope > [data-add-product]').click();await page.locator('[name="products[0][title]"]').fill('First product');
  await registration.locator(':scope > [data-add-product]').click();assert.equal(await registration.locator(':scope > [data-editor-tabs] > button:visible').count(),2);
  for(const width of [390,1280]){await page.setViewportSize({width,height:900});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Horizontal overflow at '+width);}
  assert.deepEqual(errors,[]);console.log('OK: real PHP fields, language tabs, 2/4/5 products, cap, removal, hidden-field submission, validation, keyboard-compatible tabs and mobile width.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
