window.updateContentCompletion=function(items){
 const box=document.querySelector('[data-content-completion]');if(!box||!Array.isArray(items))return;
 const done=items.filter(x=>x.done).length,percent=items.length?Math.round(done/items.length*100):0;
 box.replaceChildren();const title=document.createElement('strong');title.textContent=done+' / '+items.length+' étapes renseignées';box.append(title);
 const progress=document.createElement('progress');progress.max=100;progress.value=percent;progress.setAttribute('aria-label','Complétion de la fiche');box.append(progress);
 const steps=document.createElement('div');steps.className='content-completion-steps';
 items.forEach((item,index)=>{const step=document.createElement('span');step.className=item.done?'complete':'missing';step.textContent=(item.done?'✓ ':String(index+1)+'. ')+item.label;steps.append(step);});box.append(steps);
};
const initial=document.querySelector('[data-completion-initial]');if(initial)window.updateContentCompletion(JSON.parse(initial.textContent));
