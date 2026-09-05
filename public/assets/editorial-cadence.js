(function(){
 const rows=document.querySelector('[data-cadence-rows]'),template=document.querySelector('[data-cadence-template]'),add=document.querySelector('[data-add-cadence]'),empty=document.querySelector('[data-cadence-empty]');
 if(!rows||!template||!add)return;
 function renumber(){const items=rows.querySelectorAll('[data-cadence-row]');items.forEach((row,index)=>{row.querySelector('[data-cadence-number]').textContent=index+1;row.querySelectorAll('[name^="cadence["]').forEach(field=>field.name=field.name.replace(/^cadence\[\d+\]/,'cadence['+index+']'))});empty.hidden=items.length>0;const count=rows.closest('.cadence-builder').querySelector('summary small');if(count)count.textContent='Optionnel · '+items.length+' créneau(x)'}
 add.addEventListener('click',()=>{rows.appendChild(template.content.cloneNode(true));renumber();rows.lastElementChild?.querySelector('select,input')?.focus()});
 rows.addEventListener('click',event=>{const button=event.target.closest('[data-remove-cadence]');if(!button)return;button.closest('[data-cadence-row]').remove();renumber()});renumber();
})();
