(function () {
    const client = document.getElementById('publishClient');
    const project = document.getElementById('publishProject');
    if (!client || !project) return;
    function refresh() {
        Array.from(project.options).forEach(option => {
            option.hidden = !!option.dataset.client && option.dataset.client !== client.value;
            option.disabled = option.hidden;
        });
        if (project.selectedOptions[0]?.disabled) project.value = '';
        let visible = 0;
        document.querySelectorAll('.target-card').forEach(card => {
            const projects = (card.dataset.projects || '').split(',').filter(Boolean);
            card.hidden = !client.value || !project.value || card.dataset.client !== client.value || !projects.includes(project.value);
            card.querySelectorAll('input, textarea').forEach(input => {
                input.disabled = card.hidden;
                if (card.hidden && input.type === 'checkbox') input.checked = false;
            });
            if (!card.hidden) visible++;
        });
        const hint = document.querySelector('[data-destination-hint]');
        if (hint) hint.textContent = visible ? visible + ' destination(s) autorisée(s) pour ce projet.' : 'Choisissez un client et un projet ayant des pages associées. Gérez ces associations depuis le projet.';
    }
    client.addEventListener('change', refresh);
    project.addEventListener('change', refresh);
    refresh();
    const mode = document.getElementById('publishMode');
    const field = document.getElementById('scheduleField');
    const date = field?.querySelector('input');
    function schedule() {
        const immediate = mode?.value === 'Now';
        if (field) field.hidden = immediate;
        if (date) { date.required = !immediate; date.disabled = immediate; if (immediate) date.value = ''; }
    }
    mode?.addEventListener('change', schedule);
    schedule();
})();
