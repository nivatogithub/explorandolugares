// Read-only local audit/tour. Never submit operational forms or send mail.
const fs=require('node:fs/promises'),path=require('node:path');
const root=path.resolve(__dirname,'../..'),{chromium}=require(path.join(root,'.local/playwright-video/node_modules/playwright'));
const base=process.argv[2]||'http://127.0.0.1:8097',credentialsPath=process.argv[3],out=path.resolve(process.argv[4]||path.join(root,'.local/admin-recorrido'));
if(!/^http:\/\/127\.0\.0\.1:\d+$/.test(base)||!out.startsWith(root+'/.local/'))throw Error('Solo validación local');
const tour=process.argv.includes('--tour');
const pages=[['Atención a comercios','atencion-comercios.php'],['Pendientes de publicación','atencion-comercios.php?view=review'],['Solicitudes de usuarios','solicitudes-publicas.php'],['Solicitudes de participación','index.php?tab=solicitudes'],['Catálogo y cobertura','negocios.php'],['Canales y responsable','contactos-negocios.php'],['Plantillas y correos','correos.php?tab=templates'],['Exportar y actualizar','actualizar-negocios.php'],['Campañas','campanas.php'],['Registro y reclamaciones','solicitudes-cuenta.php'],['Cuentas','usuarios.php'],['Suscripciones y pagos','suscripciones.php'],['Preparación editorial','editorial-negocios.php'],['Lugares','destinos.php'],['Reportes','reportes.php'],['Inteligencia e inversión','inversionistas.php'],['Costos','costos.php'],['Cancelaciones','cancelaciones.php'],['Configuración de pago','medios-pago.php'],['Configuración del portal','configuracion.php'],['Brevo','campanas.php?tab=conexion'],['Estado del sistema','index.php?tab=sistema'],['Errores de ejecución','incidencias.php'],['Base de datos','base-datos.php']];
(async()=>{
 await fs.mkdir(out,{recursive:true});const credentials=JSON.parse(await fs.readFile(credentialsPath));const browser=await chromium.launch({headless:!tour,slowMo:tour?120:0});
 const context=await browser.newContext({viewport:{width:1440,height:960},...(tour?{recordVideo:{dir:out,size:{width:1440,height:960}}}:{})});const page=await context.newPage();
 await page.goto(base+'/negocios/acceso/');await page.locator('input[name=email]').fill(credentials.administrator);await page.locator('input[name=password]').fill(credentials.password);await page.getByRole('button',{name:'Entrar a mi cuenta',exact:true}).click();await page.waitForURL(/portal|espacio/,{timeout:15000});
 const results=[];const scriptErrors=[];page.on('pageerror',e=>scriptErrors.push(e.message));
 for(const viewport of tour?[{width:1440,height:960}]:[{width:1440,height:960},{width:390,height:844}]){
  await page.setViewportSize(viewport);
  for(const [i,[name,route]] of pages.entries()){
   const failures=[];const listener=r=>{if(r.url().startsWith(base+'/assets/')&&r.status()>=400)failures.push(r.url());};page.on('response',listener);
   const response=await page.goto(base+'/admin/'+route,{waitUntil:'networkidle'});
   const checks=await page.evaluate(()=>({title:document.querySelector('h1')?.textContent?.trim(),overflow:document.documentElement.scrollWidth>innerWidth+1,selects:document.querySelectorAll('select:not([multiple])').length,enhanced:document.querySelectorAll('select[data-floating-select=ready]').length,styles:document.styleSheets.length,fatal:/Fatal error|Warning:|Parse error|Uncaught/.test(document.body.innerText),broken:[...document.images].filter(i=>i.getBoundingClientRect().width&&i.complete&&!i.naturalWidth).length}));
   const filename=viewport.width+'-'+String(i+1).padStart(2,'0')+'.png';
   if(tour){await page.evaluate(text=>{const label=document.createElement('div');label.textContent=text;Object.assign(label.style,{position:'fixed',bottom:'12px',left:'20px',zIndex:99999,background:'#fff',color:'#172b42',border:'1px solid #ccc',borderRadius:'8px',padding:'12px 18px',font:'16px system-ui',boxShadow:'0 2px 8px #0002'});document.body.append(label);},(i+1)+' / '+pages.length+' · '+name+' · Revisión sin modificar datos');await page.waitForTimeout(1300);}
   await page.screenshot({path:path.join(out,filename)});
   if(tour){await page.evaluate(()=>window.scrollBy({top:innerHeight*.7,behavior:'smooth'}));await page.waitForTimeout(1300);}
   results.push({name,route,width:viewport.width,status:response.status(),...checks,failures,screenshot:filename});page.off('response',listener);
  }
 }
 await context.close();await browser.close();await fs.writeFile(path.join(out,'resultado.json'),JSON.stringify({results,scriptErrors},null,2));
 const escape=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 await fs.writeFile(path.join(out,'index.html'),'<!doctype html><html lang="es"><meta charset="utf-8"><title>Revisión del panel</title><style>body{font:16px system-ui;margin:30px;background:#f7f7f7}article{background:white;padding:20px;margin:18px 0}img{max-width:100%;max-height:620px;object-fit:contain;object-position:left top}summary{cursor:pointer}table{border-collapse:collapse}td,th{padding:8px;border:1px solid #ccc}</style><h1>Revisión local del panel</h1><p>Recorrido de solo lectura. No se enviaron correos ni se modificaron cuentas, negocios, pagos o estadísticas.</p>'+results.map(r=>'<article><h2>'+escape(r.name)+' · '+r.width+' px</h2><p>HTTP '+r.status+' · '+r.enhanced+'/'+r.selects+' selectores compartidos · Desbordamiento: '+r.overflow+' · Error PHP: '+r.fatal+'</p><details><summary>Ver captura</summary><img loading="lazy" src="'+r.screenshot+'"></details></article>').join('')+'</html>');
 const bad=results.filter(r=>r.status!==200||r.fatal||r.overflow||r.broken||r.failures.length||r.enhanced!==r.selects);console.log(JSON.stringify({checked:results.length,issues:bad,scriptErrors},null,2));if(bad.length||scriptErrors.length)process.exitCode=1;
})().catch(e=>{console.error(e.message);process.exitCode=1});
