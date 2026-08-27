(function(){
 if(!location.pathname.includes('/calendrier/'))return;
 const key='strax-calendar-position:'+location.pathname+location.search;
 function remember(){try{sessionStorage.setItem(key,JSON.stringify({y:window.scrollY,at:Date.now()}));}catch(e){}}
 document.addEventListener('submit',function(event){if(event.target.querySelector('[name="manager_action"][value="move_publication_date"]'))remember();},true);
 try{const saved=JSON.parse(sessionStorage.getItem(key)||'null');sessionStorage.removeItem(key);if(saved&&Date.now()-saved.at<120000){window.addEventListener('load',()=>requestAnimationFrame(()=>window.scrollTo({top:saved.y,behavior:'instant'})));}}catch(e){}
})();
