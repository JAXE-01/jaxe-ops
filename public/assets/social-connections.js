(function(){
  const bulk=document.querySelector('[data-connection-bulk]'),feedback=document.querySelector('[data-connection-feedback]');
  const checks=()=>Array.from(document.querySelectorAll('[data-connection-select]'));
  const message=(text,error=false)=>{if(!feedback)return;feedback.hidden=false;feedback.textContent=text;feedback.classList.toggle('error',error)};
  const sync=()=>{if(!bulk)return;const n=checks().filter(x=>x.checked).length;bulk.querySelector('[data-bulk-count]').textContent=n+' sélectionné(s)';bulk.querySelector('[data-bulk-submit]').disabled=!n;const all=bulk.querySelector('[data-select-all]');all.checked=n>0&&n===checks().length;all.indeterminate=n>0&&n<checks().length};
  bulk?.querySelector('[data-select-all]')?.addEventListener('change',e=>{checks().filter(x=>!x.closest('[data-connection-row]').hidden).forEach(x=>x.checked=e.target.checked);sync()});
  bulk?.querySelector('[data-bulk-action]')?.addEventListener('change',e=>{const client=bulk.querySelector('[data-bulk-client]');client.hidden=e.target.value!=='assign';client.required=e.target.value==='assign'});
  document.addEventListener('change',e=>{if(e.target.matches('[data-connection-select]'))sync()});
  async function submit(form){const button=form.querySelector('button[type=submit]');button.disabled=true;try{const response=await fetch(form.action||location.href,{method:'POST',body:new FormData(form),credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});const data=await response.json();if(!response.ok||!data.success)throw new Error(data.message||'Opération impossible.');message(data.message);return true}catch(error){message(error.message||'Opération impossible.',true);return false}finally{button.disabled=false}}
  bulk?.addEventListener('submit',async e=>{e.preventDefault();if(bulk.querySelector('[data-bulk-action]').value==='remove'&&!confirm('Retirer tous les comptes sélectionnés ?'))return;if(await submit(bulk))location.reload()});
  document.querySelectorAll('[data-account-form]').forEach(form=>form.addEventListener('submit',async e=>{e.preventDefault();if(form.hasAttribute('data-confirm-remove')&&!confirm('Retirer ce compte social ? Son historique sera conservé.'))return;if(await submit(form)){if(form.querySelector('[name=action]').value==='remove')form.closest('[data-connection-row]')?.remove();sync()}}));sync();
})();
