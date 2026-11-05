// Existing local preproduction only; no external navigation, purchases or messages.
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'/Users/nivato/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root=path.resolve(__dirname,'../..'),base='http://127.0.0.1:8088';
const input=JSON.parse(fs.readFileSync(0,'utf8'));assert.equal(input.base,base);
const out=path.join(root,'.local/preprod/regresion/browser');fs.mkdirSync(out,{recursive:true});
const results=[],errors=[];
async function criterion(id,label,fn){try{await fn();results.push({id,label,passed:true});}catch(e){results.push({id,label,passed:false,error:e.message});}console.log(JSON.stringify(results.at(-1)));}
async function choose(page,selector,value){
 const native=page.locator(selector);await native.waitFor();
 const label=await native.locator('option').evaluateAll((xs,v)=>xs.find(x=>x.value===v)?.textContent,value);assert(label,'Missing option '+selector+' '+value);
 const box=native.locator('..');const trigger=box.locator('.floating-select-trigger');
 if(await trigger.count()){await trigger.click();await box.getByRole('option',{name:label,exact:true}).click();}
 else await native.selectOption(value);
}
async function visibleImages(page){
 const images=page.locator('img');
 for(let i=0;i<await images.count();i++){
  const img=images.nth(i);if(!await img.isVisible())continue;
  await img.scrollIntoViewIfNeeded();await img.evaluate(e=>e.loading='eager');
  await page.waitForFunction(e=>e.complete&&e.naturalWidth>0,await img.elementHandle(),{timeout:10000});
 }
}
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'});
 try{
  for(const viewport of [{width:1440,height:1000},{width:390,height:844}]){
   const size=viewport.width===390?'mobile':'desktop';const context=await browser.newContext({viewport});
   await context.addInitScript(()=>{localStorage.setItem('el_measurement_excluded_v1','1');navigator.clipboard.writeText=async text=>{window.__copiedInvite=text;};});
   await context.route('**/*',route=>{const u=new URL(route.request().url());return u.origin===base||['data:','blob:'].includes(u.protocol)?route.continue():route.abort();});
   const page=await context.newPage();page.setDefaultTimeout(10000);page.on('pageerror',e=>errors.push({size,error:e.message}));
   await criterion('UI-01',size+': modal, session, account tabs, mobile menu and logout',async()=>{
    await page.goto(base+'/');await page.locator('.account-entry a[href*="/negocios/acceso/"]').click();
    const dialog=page.locator('[data-account-auth-dialog]');await dialog.waitFor({state:'visible'});
    await dialog.locator('[name=email]').fill(input.email);await dialog.locator('[name=password]').fill(input.password);await dialog.locator('button[type=submit]').click();
    await dialog.waitFor({state:'detached'});await page.locator('.header-language-bar .account-name').waitFor({state:'visible'});
    assert(await page.locator('.header-language-options').isVisible());
    if(size==='mobile'){await page.locator('.menu-toggle').click();assert.equal(await page.locator('.menu-toggle').getAttribute('aria-expanded'),'true');await page.keyboard.press('Escape');assert.equal(await page.locator('.menu-toggle').getAttribute('aria-expanded'),'false');}
    await page.locator('.account-entry a[href="/negocios/portal/"]').click();await page.locator('.account-tabs a[href*="tab=personal"]').waitFor();
    await page.locator('.account-tabs a[href*="tab=business"]').click();
    await page.waitForURL(u=>u.pathname==='/negocios/portal/'&&u.searchParams.get('tab')==='business');
    await page.waitForLoadState('networkidle');await page.evaluate(()=>document.fonts.ready);
    await page.getByRole('heading',{name:'Mis negocios',exact:true}).waitFor({state:'visible'});
    const layout=await page.evaluate(()=>({viewport:innerWidth,documentWidth:document.documentElement.scrollWidth,bodyWidth:document.body.scrollWidth,offenders:[...document.querySelectorAll('body *')].filter(e=>e.getBoundingClientRect().right>innerWidth+1&&!e.closest('.destination-admin-table')&&getComputedStyle(e).display!=='none').slice(0,12).map(e=>({tag:e.tagName,id:e.id,className:typeof e.className==='string'?e.className:'svg',right:Math.round(e.getBoundingClientRect().right)}))}));
    fs.writeFileSync(path.join(out,size+'-account-layout.json'),JSON.stringify(layout,null,2)+'\n');
    assert(layout.documentWidth<=layout.viewport+1,'Account page horizontal overflow: '+JSON.stringify(layout));
    await page.screenshot({path:path.join(out,size+'-account.png'),fullPage:true});
    await page.locator('.account-entry .account-logout-button').click();
    const state=await page.request.get(base+'/api/account-session.php');assert.equal((await state.json()).authenticated,false);
    const protectedPage=await page.request.get(base+'/negocios/portal/');assert((await protectedPage.text()).includes('name="password"'));
   });
   await criterion('LU-UI',size+': live directory filters, cards, experiences and three languages',async()=>{
    await page.goto(base+'/lugares/');await page.locator('[data-place-filters] [name=q]').fill('la fe');
    await page.waitForURL(u=>u.searchParams.get('q')==='la fe');await page.locator('a[href="/lugares/el-retiro/"]').first().waitFor({state:'visible'});
    for(const prefix of ['','/en','/pt-br']){
     await page.goto(base+prefix+'/lugares/jerico/');await page.locator('#catalogo-local').waitFor();
     assert(await page.locator('.destination-experience-card').count()>=3);assert(await page.locator('#directory-tab-todos').count());
     const tabs=page.locator('[role=tab][aria-controls="directory-panel"]');assert(await tabs.count()>1);
     await tabs.nth(1).click();assert.equal(await tabs.nth(1).getAttribute('aria-selected'),'true');
     await page.locator('#directory-tab-todos').click();assert.equal(await page.locator('#directory-tab-todos').getAttribute('aria-selected'),'true');
     const more=page.locator('#cargar-mas-atractivos');if(await more.isVisible())await more.click();
     assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Horizontal overflow on '+prefix);
     await visibleImages(page);
    }
    await page.screenshot({path:path.join(out,size+'-place.png'),fullPage:true});
   });
   await criterion('UI-02',size+': coverage map, sectors, audience, invitation and API errors',async()=>{
    await page.goto(base+'/cobertura/colombia/');await page.locator('#map g[data-code="05"]').waitFor({state:'visible'});
    if(!await page.locator('[data-map-sector="andina"]').isVisible())await page.locator('.sector-entry > summary').click();
    await page.locator('[data-map-sector="andina"]').click();await page.waitForFunction(()=>document.querySelector('[data-map-sector="andina"]').getAttribute('aria-pressed')==='true'&&document.querySelector('#map').getAttribute('aria-busy')==='false');
    await page.locator('#map g[data-code="05"]').focus();await page.keyboard.press('Enter');await page.waitForFunction(()=>document.querySelector('#map .department-focus[data-code="05"]'));
    assert(new URL(page.url()).searchParams.toString().includes('05'));
    await page.getByRole('tab',{name:'Visitantes y crecimiento',exact:true}).click();assert(await page.locator('#panel-visitantes').isVisible());
    await choose(page,'#audience-period','90');await page.waitForFunction(()=>document.querySelector('#audience-scope').textContent.includes('90'));
    await page.getByRole('tab',{name:/Haz parte/}).click();await page.locator('[data-invite="people"]').click();
    await page.locator('#invite-preview').waitFor({state:'visible'});assert((await page.locator('#invite-message').inputValue()).length>30);
    assert((await page.locator('#invite-whatsapp').getAttribute('href')).startsWith('https://wa.me/'));
    await page.locator('#invite-copy').click();await page.waitForFunction(()=>window.__copiedInvite?.length>30);
    await page.screenshot({path:path.join(out,size+'-coverage.png'),fullPage:true});
    const fail=await context.newPage();try{await fail.route('**/api/coverage.php*',r=>r.fulfill({status:503,json:{error:'synthetic_unavailable'}}));await fail.goto(base+'/cobertura/colombia/');await fail.locator('#notice:not([hidden])').waitFor();assert.match(await fail.locator('#notice').innerText(),/No pudimos cargar/);}finally{await fail.close();}
   });
   await criterion('SV-operador',size+': independent optional operator price and checkout selection',async()=>{
    await page.goto(base+'/negocios-locales/suscripciones/');await page.locator('[data-sim-status]').filter({hasText:/Tarifas vigentes/}).waitFor();
    const extra=page.locator('[data-sim-experiences]');assert(await extra.isHidden());
    if(size==='mobile')await page.locator('.subscription-mobile-picker button').nth(1).click();else await page.locator('[name=sim_subscription][value=featured]').check();
    await choose(page,'[data-sim-country]','CO');await choose(page,'[data-sim-department]','Antioquia');
    const jerico=await page.locator('[data-sim-place] option').evaluateAll(xs=>xs.find(x=>/Jericó/.test(x.textContent))?.value);assert(jerico);await choose(page,'[data-sim-place]',jerico);
    const options=await page.locator('[data-sim-experience] option').evaluateAll(xs=>xs.filter(x=>x.value!=='0').map(x=>x.value));assert(options.length>=3);
    const catalog=await(await page.request.get(base+'/api/subscription-prices.php')).json();
    for(const period of ['monthly','quarterly','semiannual','annual']){
     await choose(page,'[data-sim-frequency]',period);await choose(page,'[data-sim-experience]',options[0]);
     const buy=new URL(await page.locator('[data-sim-buy]').getAttribute('href'),base);assert.equal(buy.searchParams.get('operator'),'1');assert.equal(buy.searchParams.get('experience'),options[0]);assert.equal(buy.searchParams.get('period'),period);
     const amount=catalog.operator[period].amount;assert(amount>0);const extraText=await page.locator('[data-sim-extra]').innerText();assert(extraText.replace(/\D/g,'').includes(String(Math.trunc(amount))),'Operator amount differs from current catalog');
     assert.equal(await page.locator('[name=sim_subscription]:checked').inputValue(),'featured');
    }
    if(size==='mobile')await page.locator('.subscription-mobile-picker button').nth(0).click();else await page.locator('[name=sim_subscription][value=free]').check();
    assert(await extra.isHidden());assert.equal(await page.locator('[data-sim-experience]').inputValue(),'0');
    assert.equal(new URL(await page.locator('[data-sim-buy]').getAttribute('href'),base).searchParams.get('operator'),'0');
    await page.screenshot({path:path.join(out,size+'-subscriptions.png'),fullPage:true});
   });
   await context.close();
  }
 }finally{await browser.close();}
 const report={base,production_accessed:false,measurement_excluded:true,results,script_errors:errors,passed:results.every(r=>r.passed)&&errors.length===0};
 fs.writeFileSync(path.join(out,'results.json'),JSON.stringify(report,null,2)+'\n');console.log(JSON.stringify(report,null,2));process.exitCode=report.passed?0:1;
})().catch(e=>{console.error(e.message);process.exit(1);});
