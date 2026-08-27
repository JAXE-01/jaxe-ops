// Logic-level DOM test, not a substitute for browser visual QA.
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const handlers={};
function control(id,value=''){return {value,addEventListener(event,fn){handlers[id+':'+event]=fn;}};}
const client=control('client'),project=control('project');
project.options=[{value:'',dataset:{}},{value:'10',dataset:{client:'1'}},{value:'20',dataset:{client:'2'}}];
Object.defineProperty(project,'selectedOptions',{get(){return [project.options.find(x=>x.value===project.value)];}});
const mode=control('mode','Scheduled'),date={value:'2026-09-01T10:00'},field={querySelector(){return date;}};
function card(client,projects){const controls=[{type:'checkbox',checked:true},{type:'textarea'}];return {dataset:{client,projects},controls,querySelectorAll(){return controls;}};}
const cards=[card('1','10'),card('1','11'),card('2','20'),card('1','')],hint={};
const document={getElementById(id){return {publishClient:client,publishProject:project,publishMode:mode,scheduleField:field}[id];},querySelectorAll(){return cards;},querySelector(){return hint;}};
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname,'../public/assets/social-context.js'),'utf8'),{document});
assert(cards.every(x=>x.hidden&&x.controls.every(y=>y.disabled)));
client.value='1';handlers['client:change']();project.value='10';handlers['project:change']();
assert.equal(cards[0].hidden,false);assert(cards.slice(1).every(x=>x.hidden));
cards[0].controls[0].checked=true;client.value='2';handlers['client:change']();
assert.equal(project.value,'');assert.equal(cards[0].controls[0].checked,false);
project.value='20';handlers['project:change']();assert.equal(cards[2].hidden,false);
mode.value='Now';handlers['mode:change']();assert(field.hidden&&date.disabled&&!date.required);assert.equal(date.value,'');
mode.value='Scheduled';handlers['mode:change']();assert(!field.hidden&&!date.disabled&&date.required);
console.log('OK: client/project filtering, unmapped hidden, stale selection cleared, immediate date disabled/cleared, scheduled date required.');
