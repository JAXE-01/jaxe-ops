(() => {
  const form = document.getElementById('reporting-filter-form');
  if (!form) return;
  form.dataset.noGlobalLoader = '';
  const status = document.createElement('div');
  status.className = 'report-status'; status.setAttribute('role', 'status');
  form.after(status);
  let pending, generation = 0;
  async function refresh(url = new URL(form.action)) {
    const reset = typeof url === 'string';
    if (!(url instanceof URL)) url = new URL(url, location.href);
    else url.search = new URLSearchParams(new FormData(form)).toString();
    pending?.abort(); pending = new AbortController();
    const current = ++generation;
    const timer = setTimeout(() => { status.textContent = 'Actualisation…'; status.classList.add('busy'); }, 450);
    try {
      const response = await fetch(url, { signal: pending.signal, credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'} });
      if (!response.ok) throw new Error();
      const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
      if (current !== generation) return;
      if (!doc.getElementById('reporting-results')) throw new Error();
      if(reset && doc.getElementById('reporting-filter-form')) form.innerHTML=doc.getElementById('reporting-filter-form').innerHTML;
      const opened = [...document.querySelectorAll('.report-columns[open]')].map(el => [...document.querySelectorAll('.report-columns')].indexOf(el));
      for (const id of ['reporting-results', 'reporting-page', 'report-export-actions']) {
        const old = document.getElementById(id), next = doc.getElementById(id);
        if (old && next) old.replaceWith(next);
      }
      opened.forEach(i => { const el = document.querySelectorAll('.report-columns')[i]; if(el) el.open = true; });
      history.replaceState({}, '', url);
      status.textContent = '';
    } catch (e) {
      if (current === generation && e.name !== 'AbortError') status.textContent = 'Actualisation impossible. Vérifiez votre connexion ou reconnectez-vous, puis réessayez.';
    } finally {
      clearTimeout(timer);
      if (current === generation) status.classList.remove('busy');
    }
  }
  form.addEventListener('submit', e => { e.preventDefault(); refresh(); });
  document.addEventListener('change', e => {
    if (e.target.form !== form) return;
    if (e.target.name === 'client_id') form.elements.connection_id.value = '';
    refresh();
  });
  document.addEventListener('click', async e => {
    const resetLink=e.target.closest('a');
    if(resetLink && form.contains(resetLink)){e.preventDefault();refresh(form.action);return;}
    const sort = e.target.closest('.report-sort');
    if (sort) {
      form.elements.direction.value = form.elements.sort.value === sort.dataset.sort && form.elements.direction.value === 'desc' ? 'asc' : 'desc';
      form.elements.sort.value = sort.dataset.sort; refresh(); return;
    }
    const link = e.target.closest('#report-export-actions a');
    if (!link) return;
    e.preventDefault();
    if(link.dataset.busy) return;
    link.dataset.busy = '1'; link.setAttribute('aria-disabled', 'true');
    const timer = setTimeout(() => { status.textContent = 'Préparation du document…'; status.classList.add('busy'); },450);
    try {
      const exportUrl = new URL(form.action);
      exportUrl.search = new URLSearchParams(new FormData(form)).toString();
      const exportOptions = new URL(link.href).searchParams;
      exportUrl.searchParams.set('export',exportOptions.get('export'));
      exportUrl.searchParams.set('report_type',exportOptions.get('report_type'));
      const response = await fetch(exportUrl, {credentials:'same-origin'});
      if(!response.ok || /text\/html/.test(response.headers.get('Content-Type') || '')) throw new Error();
      const blob = await response.blob();
      const filename = /filename="([^"]+)"/.exec(response.headers.get('Content-Disposition') || '')?.[1] || 'rapport';
      const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = filename;
      document.body.append(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href),60000);
      status.textContent = 'Document téléchargé.';
    } catch { status.textContent = 'Export impossible. Vérifiez votre connexion ou votre session.'; }
    finally { clearTimeout(timer); status.classList.remove('busy'); delete link.dataset.busy; link.removeAttribute('aria-disabled'); }
  });
  document.querySelectorAll('.social-reporting-actions form').forEach(action => {action.dataset.noGlobalLoader = '';});
  document.addEventListener('submit', async e => {
      const action=e.target;
      if(!action.matches('.social-reporting-actions form,.report-delete') || e.defaultPrevented) return;
      e.preventDefault(); if(action.dataset.busy) return;
      const payload = new FormData(action);
      action.dataset.busy = '1';
      const button = action.querySelector('[type="submit"]'); if(button) button.disabled = true;
      const timer = setTimeout(() => {status.textContent = 'Collecte en cours…';status.classList.add('busy');},450);
      try {
        const response = await fetch(action.action,{method:'POST',body:payload,credentials:'same-origin'});
        const doc = new DOMParser().parseFromString(await response.text(),'text/html');
        if(!response.ok || !doc.getElementById('reporting-results')) throw new Error();
        const flash = doc.querySelector('[data-flash-message]');
        if(flash) window.AppUI?.toast(flash.dataset.flashType,flash.dataset.flashMessage);
        clearTimeout(timer); await refresh();
      } catch {status.textContent = 'Résultat non confirmé. Vérifiez les collectes avant de relancer pour éviter un doublon.';}
      finally {clearTimeout(timer);status.classList.remove('busy');delete action.dataset.busy;if(button) button.disabled=false;}
  });
})();
