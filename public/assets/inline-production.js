(() => {
  const panel = document.querySelector('[data-inline-production]');
  if (!panel) return;
  const host = panel.querySelector('[data-production-host]');
  const status = panel.querySelector('[data-production-status]');
  const endpoint = new URL(panel.dataset.inlineProduction, location.href);
  if (endpoint.origin !== location.origin) return;
  let dirty = false;
  async function load() {
    try {
      const response = await fetch(endpoint, {credentials: 'same-origin'});
      if (!response.ok) throw new Error('Cette étape est indisponible.');
      const page = new DOMParser().parseFromString(await response.text(), 'text/html');
      const source = page.querySelector('form[data-task-type="' + panel.dataset.productionType + '"]');
      if (!source) throw new Error('Étape inaccessible avec vos droits actuels, ou session expirée.');
      const form = document.importNode(source, true);
      // Each phase uses its original endpoint and server-side authorization.
      // No implicit autosave or validation when merely opening this workspace.
      form.action = endpoint.href;
      form.removeAttribute('data-autosave-form');
      form.removeAttribute('data-autosave-endpoint');
      form.querySelectorAll('[data-autosave-status]').forEach(node => node.remove());
      form.addEventListener('input', () => { dirty = true; });
      form.addEventListener('change', () => { dirty = true; });
      form.addEventListener('submit', () => { dirty = false; });
      host.replaceChildren(form);
      status.textContent = 'Enregistrement séparé ; les prérequis et droits de cette étape restent applicables.';
    } catch(error) {
      status.textContent = error.message || 'Chargement impossible. Ouvrez la vue détaillée.';
    }
  }
  window.addEventListener('beforeunload', event => {
    if (dirty) { event.preventDefault(); event.returnValue = ''; }
  });
  load();
})();
