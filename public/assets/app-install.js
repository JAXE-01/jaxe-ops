(() => {
  const button = document.getElementById('install-app');
  if (!button || matchMedia('(display-mode: standalone)').matches || navigator.standalone) return;
  let prompt;
  window.addEventListener('beforeinstallprompt', e => {e.preventDefault();prompt=e;button.hidden=false;});
  const ios = /iPhone|iPad|iPod/.test(navigator.userAgent) || (navigator.platform==='MacIntel' && navigator.maxTouchPoints>1);
  if(ios) button.hidden=false;
  button.addEventListener('click',async()=>{
    if(prompt){await prompt.prompt();await prompt.userChoice;prompt=null;button.hidden=true;}
    else window.AppUI?.toast('info','Pour installer Strax : Partager → Sur l’écran d’accueil.');
  });
  window.addEventListener('appinstalled',()=>{button.hidden=true;prompt=null;});
})();
