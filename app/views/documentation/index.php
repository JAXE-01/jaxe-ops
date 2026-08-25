<?php
$filters    = $filters ?? [];
$clients    = is_array($clients ?? null) ? $clients : [];
$documents  = is_array($documents ?? null) ? $documents : [];
$page       = (int) ($page ?? 1);
$totalPages = (int) ($totalPages ?? 1);
$total      = (int) ($total ?? 0);

$periodOptions = [
    ''               => 'Toutes les periodes / mois',
    'current_month'  => 'Mois en cours',
    'prev_month'     => 'Mois precedent',
    'last_3_months'  => '3 derniers mois',
    'next_month'     => 'Mois prochain',
    'next_3_months'  => '3 prochains mois',
    'next_6_months'  => '6 prochains mois',
];
?>

<section class="panel js-ajax-list" data-list-scope="documentation">
    <div class="panel-head">
        <div>
            <h2>Documentation client</h2>
            <p class="panel-subtitle">Centralise logos, documents de marque, PDF, assets et fichiers de contexte client.</p>
        </div>
        <button class="button" type="button" id="docs-toggle-form-btn">+ Ajouter un document</button>
    </div>

    <div id="docs-add-form" hidden>
        <form method="post" enctype="multipart/form-data" class="form-grid" style="padding-top:12px;border-top:1px solid var(--border);">
            <label class="field">
                <span>Client</span>
                <select name="client_id">
                    <option value="">Sans client</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= htmlspecialchars((string) ($client['id'] ?? '')) ?>"><?= htmlspecialchars((string) ($client['entreprise'] ?? 'Client')) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>Titre</span><input type="text" name="titre" required></label>
            <label class="field"><span>Categorie</span><input type="text" name="categorie" placeholder="Logo, Brandbook, Process..."></label>
            <label class="field"><span>Date document</span><input type="date" name="date_document" value="<?= date('Y-m-d') ?>"></label>
            <label class="field"><span>Fichier</span><input type="file" name="document_file" required></label>
            <div class="form-actions">
                <button class="button" type="submit">Ajouter le document</button>
                <button class="button secondary" type="button" id="docs-cancel-btn">Annuler</button>
            </div>
        </form>
    </div>
</section>

<section class="panel">
    <form method="get" class="list-toolbar js-ajax-list-form">
        <label class="field toolbar-field">
            <span>Client</span>
            <select name="client_id">
                <option value="">Tous</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= htmlspecialchars((string) ($client['id'] ?? '')) ?>" <?= (($filters['client_id'] ?? '') === (string) ($client['id'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($client['entreprise'] ?? 'Client')) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field toolbar-field">
            <span>Periode / mois</span>
            <select name="period">
                <?php foreach ($periodOptions as $periodValue => $periodLabel): ?>
                    <option value="<?= htmlspecialchars($periodValue) ?>" <?= (($filters['period'] ?? '') === $periodValue) ? 'selected' : '' ?>><?= htmlspecialchars($periodLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field toolbar-field"><span>Du</span><input type="date" name="from" value="<?= htmlspecialchars((string) ($filters['from'] ?? '')) ?>"></label>
        <label class="field toolbar-field"><span>Au</span><input type="date" name="to" value="<?= htmlspecialchars((string) ($filters['to'] ?? '')) ?>"></label>
        <div class="toolbar-actions">
            <button class="button" type="submit">Filtrer</button>
            <a class="button secondary" href="<?= htmlspecialchars(route_url('/documentation')) ?>">Reinitialiser</a>
        </div>
    </form>

    <?php if ($total > 0): ?>
        <p class="list-count"><?= $total ?> document<?= $total > 1 ? 's' : '' ?> · page <?= $page ?> / <?= $totalPages ?></p>
    <?php endif; ?>

    <div class="table-wrap compact-table">
        <table>
            <thead><tr><th>Date</th><th>Client</th><th>Titre</th><th>Categorie</th><th>Fichier</th><th>Ajoute par</th></tr></thead>
            <tbody>
                <?php foreach ($documents as $doc): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($doc['date_document'] ?? $doc['created_at'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($doc['client_nom'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars((string) ($doc['titre'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($doc['categorie'] ?? '')) ?></td>
                        <td><a class="file-link" href="<?= htmlspecialchars(route_url('/asset/download') . '?path=' . urlencode((string) ($doc['fichier_path'] ?? '')) . '&name=' . urlencode((string) ($doc['titre'] ?? 'document'))) ?>"><?= htmlspecialchars((string) ($doc['fichier_nom'] ?? 'Fichier')) ?></a></td>
                        <td><?= htmlspecialchars((string) ($doc['created_by_nom'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($documents)): ?><tr><td colspan="6">Aucun document.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a class="button secondary" href="<?= htmlspecialchars(route_url('/documentation') . '?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">← Precedent</a>
            <?php endif; ?>
            <span class="pagination-info">Page <?= $page ?> / <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a class="button secondary" href="<?= htmlspecialchars(route_url('/documentation') . '?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">Suivant →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<script>
(function () {
    var section = document.querySelector('.js-ajax-list[data-list-scope="documentation"]');
    if (!section) { return; }

    function load(url) {
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (response) { return response.text(); })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var nextSection = doc.querySelector('.js-ajax-list[data-list-scope="documentation"]');
                if (!nextSection) { return; }
                section.innerHTML = nextSection.innerHTML;
                bind();
            });
    }

    function bind() {
        section.querySelectorAll('.pagination a').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                load(link.href);
            });
        });
        var form = section.querySelector('.js-ajax-list-form');
        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                var url = form.action || window.location.pathname;
                var query = new URLSearchParams(new FormData(form)).toString();
                load(url + (query ? ('?' + query) : ''));
            });
        }
    }

    bind();
})();
</script>

<script>
(function () {
    var btn    = document.getElementById('docs-toggle-form-btn');
    var form   = document.getElementById('docs-add-form');
    var cancel = document.getElementById('docs-cancel-btn');
    if (!btn || !form) { return; }

    btn.addEventListener('click', function () {
        var isHidden = form.hidden;
        form.hidden = !isHidden;
        btn.textContent = isHidden ? 'Masquer le formulaire' : '+ Ajouter un document';
        if (!isHidden) { form.querySelector('input[name="titre"]') && form.querySelector('input[name="titre"]').focus(); }
    });

    if (cancel) {
        cancel.addEventListener('click', function () {
            form.hidden = true;
            btn.textContent = '+ Ajouter un document';
        });
    }
})();
</script>
