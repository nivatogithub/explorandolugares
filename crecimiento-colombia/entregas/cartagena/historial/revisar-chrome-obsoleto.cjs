const {chromium}=require('/Users/nivato/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('fs');
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'});
 const page=await browser.newPage();
 await page.goto('file://'+__dirname+'/preview.html');
 const observations=[];
 for (const width of [390,1440]) {
  await page.setViewportSize({width,height:900});
  const observation=await page.evaluate(()=>({width:innerWidth,overflow:document.documentElement.scrollWidth>innerWidth,brokenImages:[...document.images].filter(i=>!i.complete||i.naturalWidth===0).length,background:getComputedStyle(document.body).backgroundColor,foreground:getComputedStyle(document.body).color,languageSections:['es','en','pt-BR'].every(lang=>[...document.querySelectorAll('summary')].some(x=>x.textContent===lang)),components:document.querySelectorAll('article[data-id]').length,portadaChoice:document.querySelector('#portada-eleccion').value,approvedInitially:[...document.querySelectorAll('article select')].filter(x=>x.value==='aprobado').length,portadaAlternatives:document.querySelectorAll('.portada-options article').length}));
  if(observation.overflow||observation.brokenImages||!observation.languageSections||observation.approvedInitially||observation.portadaChoice||observation.portadaAlternatives!==2) throw Error(JSON.stringify(observation));
  observations.push(observation);
  await page.screenshot({path:__dirname+'/preview-'+width+'.png'});
 }
 fs.writeFileSync(__dirname+'/qa-navegador.json',JSON.stringify({date:'2026-10-16',type:'private-local-preview',observations},null,2)+'\n');
 await browser.close(); console.log('PASS private preview mobile/desktop, images and three language sections.');
})().catch(error=>{console.error(error.message);process.exit(1)});
