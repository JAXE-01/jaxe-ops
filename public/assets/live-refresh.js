(function(){
  const regions=()=>Array.from(document.querySelectorAll('[data-live-refresh]'));
  let dirty=false,busy=false;
  document.addEventListener('input',e=>{if(e.target.matches('input,textarea,select,[contenteditable=true]'))dirty=true},{passive:true});
  document.addEventListener('submit',()=>{dirty=false});
  async function refresh(){
    if(busy||dirty||document.hidden||document.querySelector('dialog[open],.modal.is-open'))return;
    const active=document.activeElement;if(active&&active.matches('input,textarea,select,[contenteditable=true]'))return;
    const current=regions();if(!current.length)return;
    if(current.some(region=>region.matches(':hover')||region.contains(document.activeElement)))return;
    busy=true;
    try{
      const response=await fetch(location.href,{credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'text/html'}});
      if(!response.ok)return;const doc=new DOMParser().parseFromString(await response.text(),'text/html');
      current.forEach(region=>{const fresh=doc.querySelector('#'+CSS.escape(region.id));if(!fresh||fresh.innerHTML===region.innerHTML)return;const top=window.scrollY;region.innerHTML=fresh.innerHTML;region.classList.add('live-refreshed');setTimeout(()=>region.classList.remove('live-refreshed'),650);window.scrollTo({top,behavior:'auto'});});
    }catch(ignore){}finally{busy=false}
  }
  setInterval(refresh,30000);window.addEventListener('focus',()=>setTimeout(refresh,400));document.addEventListener('visibilitychange',()=>{if(!document.hidden)setTimeout(refresh,400)});
})();
