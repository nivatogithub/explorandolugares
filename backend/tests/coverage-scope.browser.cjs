const assert=require('node:assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'/Users/nivato/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
(async()=>{const browser=await chromium.launch({headless:true,channel:'chrome'});try{
 const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto(process.env.COVERAGE_BASE_URL||'http://127.0.0.1:8056/cobertura/colombia/');
 await page.waitForFunction(()=>document.querySelector('#country-target').textContent.includes('%'));
 assert.equal(await page.locator('#country-target').innerText(),'1,03%');
 assert.match(await page.locator('#country-target-detail').innerText(),/1 de 97/);
 assert.match(await page.locator('#country-target-detail').innerText(),/No es cobertura nacional completa/);
 await page.setViewportSize({width:390,height:844});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
 await page.route('**/api/cobertura.php',async route=>{const response=await route.fetch();const body=await response.json();body.publication_source_available=false;body.editorial_target.published_percent=null;body.editorial_target.published_count=null;await route.fulfill({json:body});});
 await page.reload();await page.waitForSelector('#notice:not([hidden])');assert.equal(await page.locator('#country-target').innerText(),'—');assert.deepEqual(errors,[]);
 console.log('OK: target percentage and denominator, national distinction, mobile and unavailable states');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1)});
