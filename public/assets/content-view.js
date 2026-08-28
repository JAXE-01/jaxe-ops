(()=>{
 const select=document.querySelector('[data-content-view]');if(!select)return;
 const key='strax-content-view';
 function apply(mode){
  const expanded=mode==='expanded';
  document.querySelectorAll('.content-general-panel,.tpack-composer-panel').forEach(node=>{node.open=expanded;});
  const general=document.querySelector('.content-general-panel');if(general)general.open=true;
  document.querySelector('.content-history-panel')?.toggleAttribute('hidden',!expanded);
 }
 try{select.value=localStorage.getItem(key)==='expanded'?'expanded':'focused';}catch(e){}
 apply(select.value);select.addEventListener('change',()=>{apply(select.value);try{localStorage.setItem(key,select.value);}catch(e){}});
})();
