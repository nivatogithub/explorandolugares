const fs=require('node:fs'),vm=require('node:vm');
const path=require('node:path');const html=fs.readFileSync(path.join(__dirname,'../review.html'),'utf8');
const code=html.split('<script>')[1].split('</script>')[0];const elements={};const values={};
const ctx={document:{getElementById:id=>elements[id]??={innerHTML:'',value:0,add(){},className:''}},Option:function(t,v){this.text=t;this.value=v},localStorage:{getItem:k=>values[k],setItem:(k,v)=>values[k]=v},location:{hash:''},alert:m=>{throw Error(m)},console};vm.createContext(ctx);vm.runInContext(code,ctx);
for(let i=0;i<16;i++)for(const tab of ['images','content','business']){vm.runInContext(`pick(${i});setTab('${tab}')`,ctx);if(!elements.main.innerHTML.includes('<h2>'))throw Error('Empty view');}
vm.runInContext("mark('test:asset:hash','approved');comment('test:asset:hash','Comentario de prueba')",ctx);
const saved=JSON.parse(values['el-review-16-20261104']);if(saved['test:asset:hash'].status!=='approved'||!saved['test:asset:hash'].comment)throw Error('Review persistence failed');
console.log('48 views render; approval/comment persistence passes in isolated DOM simulation. Browser visual QA not performed.');
