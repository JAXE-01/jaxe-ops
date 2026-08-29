(() => {
  document.querySelectorAll('[data-paired-task]').forEach(panel => {
    const host = panel.querySelector('[data-paired-host]');
    const status = panel.querySelector('[data-paired-status]');
    const endpoint = new URL(panel.dataset.pairedTask, location.href);
    if (endpoint.origin !== location.origin) return;
    fetch(endpoint, {credentials: 'same-origin'})
      .then(response => {
        if (!response.ok) throw new Error('Étape associée indisponible.');
        return response.text();
      })
      .then(html => {
        const page = new DOMParser().parseFromString(html, 'text/html');
        const source = page.querySelector('form[data-task-type="' + CSS.escape(panel.dataset.pairedType) + '"]');
        if (!source) throw new Error('Étape inaccessible avec vos droits actuels.');
        const form = document.importNode(source, true);
        form.action = endpoint.href;
        form.removeAttribute('data-autosave-form');
        form.removeAttribute('data-autosave-endpoint');
        form.querySelectorAll('[data-autosave-status]').forEach(node => node.remove());
        host.replaceChildren(form);
        status.textContent = 'Enregistrement séparé ; présenté ici pour traiter l’étape sans changer d’écran.';
      })
      .catch(error => { status.textContent = error.message || 'Chargement impossible.'; });
  });
})();
