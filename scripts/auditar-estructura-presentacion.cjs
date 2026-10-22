#!/usr/bin/env node
/* Read-only localhost audit. Never submit public forms, email, payment or external requests. */
const fs=require('node:fs/promises'),path=require('node:path');
(async()=>{
 const args=Object.fromEntries(process.argv.slice(2).map(v=>v.split('=')));const root=path.resolve(args['--root']||process.cwd());
 const {chromium}=require(args['--playwright']);const routes=JSON.parse(await fs.readFile(args['--routes'],'utf8'));const extract=await fs.readFile(path.join(root,'scripts/estructura-presentacion.js'),'utf8');const out=path.resolve(args['--output']);await fs.mkdir(out,{recursive:true});
 const browser=await chromium.launch({headless:args['--visible']!=='true'});const records=[];let serial=0;
 for(const role of ['anonymous','traveler','administrator'])for(const label of ['baseline','candidate']){
  const context=await browser.newContext({viewport:{width:1440,height:900}});const origin=`http://${label}.localhost:8196`;
  await context.route('**/*',r=>{const u=new URL(r.request().url());return u.origin===origin?r.continue():r.abort()});
  const page=await context.newPage();const checked=new Map();let errors=[],failures=[],requests=[];page.on('pageerror',e=>errors.push(e.message));page.on('response',r=>{requests.push({url:r.url(),status:r.status()});if(r.status()>=400)failures.push({url:r.url(),status:r.status()})});page.on('requestfailed',r=>failures.push({url:r.url(),failure:r.failure()?.errorText}));
  if(role!=='anonymous'){
   const fixture=label==='baseline'?'baseline':args['--candidate'];const creds=JSON.parse(await fs.readFile(path.join(root,'.local/unificacion',fixture,'private/review-credentials.json'),'utf8'));
   await page.goto(origin+'/negocios/acceso/');await page.locator('input[name=email]').first().fill(creds[role==='administrator'?'administrator':'traveler']);await page.locator('input[name=password]').first().fill(creds.password);await page.locator('button[type=submit]').first().click();await page.waitForTimeout(200);
  }
  const queue=routes.filter(r=>r.roles.includes(role));
  for(const item of queue){errors=[];failures=[];requests=[];const route=item.route;console.log(label,role,route);let record={label,role,route,family:item.family,source:item.source};
   try{
    const response=await page.goto(origin+route,{waitUntil:'domcontentloaded',timeout:20000});const html=await response.text();record.http=response.status();record.finalUrl=page.url();record.initial=await page.evaluate(({extract,html})=>{const fn=new Function(extract+';return presentationStructure;')();return fn(new DOMParser().parseFromString(html,'text/html'),false)},{extract,html});
    await page.waitForTimeout(400);await page.evaluate(async()=>{for(let y=0;y<document.documentElement.scrollHeight;y+=1000){scrollTo(0,y);await new Promise(r=>setTimeout(r,15))}for(const e of document.querySelectorAll('[loading=lazy]'))e.loading='eager';scrollTo(0,0)});await page.waitForTimeout(250);
    record.dom=await page.evaluate(extract=>{const fn=new Function(extract+';return presentationStructure;')();return fn(document,true)},extract);
    record.linkChecks=[];
    for(const link of record.dom.links){
      let u;try{u=new URL(link.href,page.url())}catch{record.linkChecks.push({href:link.href,state:'invalid-url'});continue}
      if(!['http:','https:'].includes(u.protocol)){record.linkChecks.push({href:link.href,state:'not-http-action'});continue}
      if(![`${label}.localhost`,'explorandolugares.com','www.explorandolugares.com'].includes(u.hostname)){record.linkChecks.push({href:link.href,state:'external-not-consulted'});continue}
      if(/(?:salir|logout|verificar-correo|cambiar-clave|confirmar|aprobar|eliminar|borrar|reactivar)/i.test(u.pathname)||u.searchParams.has('token')){record.linkChecks.push({href:link.href,state:'state-changing-or-token-not-requested'});continue}
      const key=u.pathname+u.search;
      if(!checked.has(key)){
        try{let dest=origin+key,status=0,body='',redirects=[];
          for(let i=0;i<6;i++){const r=await context.request.get(dest,{maxRedirects:0,timeout:10000});status=r.status();if(status>=300&&status<400&&r.headers().location){const next=new URL(r.headers().location,dest);redirects.push(next.pathname+next.search);dest=origin+next.pathname+next.search;continue}if((r.headers()['content-type']||'').includes('text/html'))body=await r.text();break}
          checked.set(key,{status,redirects,body});
        }catch(error){checked.set(key,{error:String(error.message)})}
      }
      const c=checked.get(key);let fragmentPresent=null;if(u.hash&&c.body){const id=decodeURIComponent(u.hash.slice(1));fragmentPresent=c.body.includes('id="'+id+'"')||c.body.includes("id='"+id+"'");if(key===new URL(page.url()).pathname+new URL(page.url()).search)fragmentPresent=await page.evaluate(id=>!!document.getElementById(id)||!!document.querySelector('[name="'+CSS.escape(id)+'"]'),id)}
      record.linkChecks.push({href:link.href,state:'internal-checked',status:c.status,redirects:c.redirects,error:c.error,fragmentPresent});
    }
    record.errors=[...errors];record.failedAssets=[...failures];record.requests=[...requests];
    record.interaction={};if(label==='candidate'&&item.family==='place')record.interaction.images=await page.locator('main img').evaluateAll(imgs=>imgs.map(el=>({src:el.getAttribute('src'),nativeDraggable:el.draggable,action:el.closest('a')?.getAttribute('href')||null})));
    if(label==='candidate'&&item.family==='admin'&&role==='administrator')record.interaction.admin=await page.evaluate(()=>({publicHeader:!!document.querySelector('[data-portal-shell]'),publicFooter:!!document.querySelector('[data-portal-footer]'),returnToPortal:!!document.querySelector('.admin-session a[href="/"]'),logoutSigned:!!document.querySelector('.admin-session form input[name=csrf]'),adminMenuLinks:[...document.querySelectorAll('.admin-sidebar nav a')].map(a=>a.getAttribute('href'))}));
    if(args['--visible']==='true')await page.waitForTimeout(3000);
   }catch(error){record.error=String(error.message)}
   await fs.writeFile(path.join(out,`${serial++}.json`),JSON.stringify(record,null,2));records.push({file:`${serial-1}.json`,...Object.fromEntries(['label','role','route','family','http','finalUrl','error'].map(k=>[k,record[k]]))});await fs.writeFile(path.join(out,'index.json'),JSON.stringify(records,null,2));
  }
  await context.close();
 }
 await browser.close();console.log('Completed structural pages',records.length);
})().catch(e=>{console.error(e);process.exit(1)});
