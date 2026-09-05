(function(){
  const maps={
    bold:{upper:0x1D5D4,lower:0x1D5EE,digit:0x1D7EC},
    italic:{upper:0x1D608,lower:0x1D622,digit:null},
    boldItalic:{upper:0x1D63C,lower:0x1D656,digit:0x1D7EC},
    mono:{upper:0x1D670,lower:0x1D68A,digit:0x1D7F6}
  };
  function styled(text,style){const m=maps[style];return Array.from(text).map(ch=>{const cp=ch.codePointAt(0);if(cp>=65&&cp<=90)return String.fromCodePoint(m.upper+cp-65);if(cp>=97&&cp<=122)return String.fromCodePoint(m.lower+cp-97);if(m.digit&&cp>=48&&cp<=57)return String.fromCodePoint(m.digit+cp-48);return ch}).join('')}
  function transformSelection(area,style){const start=area.selectionStart,end=area.selectionEnd;if(start===end)return;const before=area.value.slice(0,start),selection=area.value.slice(start,end),after=area.value.slice(end),value=styled(selection,style);area.value=before+value+after;area.focus();area.setSelectionRange(start,start+value.length);area.dispatchEvent(new Event('input',{bubbles:true}))}
  function update(area){const box=area.closest('.caption-editor');if(!box)return;const count=box.querySelector('[data-char-count]');if(count)count.textContent=Array.from(area.value).length+' caractères';document.querySelectorAll('[data-caption-preview]').forEach(x=>x.textContent=area.value||'Votre aperçu apparaîtra ici…')}
  document.addEventListener('click',event=>{const button=event.target.closest('[data-text-style]');if(button){event.preventDefault();const area=button.closest('.caption-editor')?.querySelector('textarea');if(area)transformSelection(area,button.dataset.textStyle);return}const hash=event.target.closest('[data-add-hashtags]');if(hash){event.preventDefault();const editor=hash.closest('.caption-editor'),area=editor?.querySelector('textarea'),input=editor?.querySelector('[data-hashtags]');if(!area||!input)return;const tags=input.value.split(/[\s,;]+/).filter(Boolean).map(x=>'#'+x.replace(/^#|[^\p{L}\p{N}_]/gu,'')).filter(x=>x.length>1);if(tags.length){area.value=area.value.trimEnd()+'\n\n'+Array.from(new Set(tags)).join(' ');input.value='';update(area)}}});
  document.addEventListener('input',event=>{if(event.target.matches('.caption-editor textarea'))update(event.target)});
  document.querySelectorAll('.caption-editor textarea').forEach(update);
  const media=document.querySelector('input[name="media_file"]');
  if(media){media.accept='image/jpeg,image/png,video/mp4';const hint=media.closest('label')?.querySelector('small');if(hint)hint.textContent='JPEG/PNG pour Meta, MP4 requis pour YouTube (100 Mo max).';}
  const composeTitle=document.querySelector('#composeDialog h2');if(composeTitle)composeTitle.textContent='Composer pour plusieurs réseaux';
})();
