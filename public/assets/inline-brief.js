(() => {
  const panel = document.querySelector('[data-inline-brief]');
  if (!panel) return;
  const status = panel.querySelector('[data-brief-status]');
  const host = panel.querySelector('[data-brief-host]');
  const endpoint = new URL(panel.dataset.inlineBrief, location.href);
  if (endpoint.origin !== location.origin) return;
  let loaded = false, loading = false, dirty = false;
  document.querySelectorAll('[data-open-inline-brief]').forEach(link=>link.addEventListener('click',()=>{panel.open=true;}));
  panel.addEventListener('toggle', async () => {
    if (!panel.open || loaded || loading) return;
    loading = true;
    status.textContent = 'Chargement du brief…';
    try {
      const response = await fetch(endpoint, {credentials: 'same-origin'});
      if (!response.ok) throw new Error('Le brief est indisponible. Utilisez la vue détaillée.');
      const page = new DOMParser().parseFromString(await response.text(), 'text/html');
      const source = page.querySelector('form[data-brief-editor]');
      if (!source) throw new Error('Le brief n’est pas accessible avec vos droits actuels, ou votre session a expiré.');
      const form = document.importNode(source, true);
      form.action = endpoint.href;
      form.removeAttribute('data-autosave-form');
      // Keep one explicit save action here. Invalidation/file deletion remain in the detailed workspace.
      form.querySelectorAll('[name="manager_action"],.icon-link.danger').forEach(node => node.remove());
      form.addEventListener('input', () => {dirty = true; status.textContent = 'Brief modifié — enregistrez la consigne.';});
      form.addEventListener('change', () => {dirty = true;});
      form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = form.querySelector('[type="submit"]');
        if (button.disabled) return;
        const payload = new FormData(form);
        payload.set('autosave_mode','1');
        button.disabled = true;
        status.textContent = 'Enregistrement du brief…';
        // Prevent edits being mistaken for saved data while a request is running.
        const controls = [...form.elements].filter(el => !el.disabled);
        controls.forEach(el => el.disabled = true);
        try {
          const response = await fetch(endpoint, {method:'POST',body:payload,credentials:'same-origin',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
          const result = await response.json();
          if (!response.ok || !result.ok) throw new Error(result.message || 'Enregistrement refusé.');
          dirty = false;
          status.textContent = 'Brief enregistré. Les validations du workflow restent applicables.';
        } catch(error) {status.textContent = error.message || 'Échec de sauvegarde. Vos modifications sont conservées ici.';}
        finally {controls.forEach(el => el.disabled = false); button.disabled = false;}
      });
      host.replaceChildren(form);
      loaded = true;
      status.textContent = 'Brief chargé.';
    } catch(error) {status.textContent = error.message;}
    finally {loading = false;}
  });
  window.addEventListener('beforeunload', event => {if(dirty){event.preventDefault();event.returnValue='';}});
})();
