<?php
$filters        = $filters ?? [];
$clients        = is_array($clients ?? null) ? $clients : [];
$items          = is_array($items ?? null) ? $items : [];
$generatedLinks = is_array($generatedLinks ?? null) ? $generatedLinks : [];
$page           = (int) ($page ?? 1);
$totalPages     = (int) ($totalPages ?? 1);
$total          = (int) ($total ?? 0);
$currentUser    = is_array($currentUser ?? null) ? $currentUser : null;
$canOpenValidationTaskLinks = !empty($canOpenValidationTaskLinks);

$periodOptions = [
    ''               => 'Toutes les periodes / mois',
    'current_month'  => 'Mois en cours',
    'prev_month'     => 'Mois precedent',
    'last_3_months'  => '3 derniers mois',
    'next_month'     => 'Mois prochain',
    'next_3_months'  => '3 prochains mois',
    'next_6_months'  => '6 prochains mois',
];

$validationOptions = [
    ''           => 'Tous',
    'Valide'     => 'Valide',
    'Non valide' => 'Non valide',
    'En attente' => 'En attente',
];

$sortOptions = [
    'date_desc' => 'Date (recent)',
    'date_asc'  => 'Date (ancien)',
    'note_desc' => 'Note client (haute)',
    'note_asc'  => 'Note client (basse)',
];

function realisation_file_preview(array $file): string {
    $path = (string) ($file['path'] ?? '');
    $name = (string) ($file['name'] ?? 'Fichier');
    if ($path === '') { return ''; }
    $ext = strtolower(pathinfo($name ?: $path, PATHINFO_EXTENSION));
    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true);
    $isVideo = in_array($ext, ['mp4', 'mov', 'webm', 'avi'], true);
    $assetUrl = htmlspecialchars(route_url('/asset/download') . '?path=' . urlencode($path) . '&name=' . urlencode($name));

    if ($isImage) {
        $thumbUrl = htmlspecialchars(route_url('/asset/download') . '?path=' . urlencode($path) . '&name=' . urlencode($name));
        return '<div class="realisation-file">'
            . '<button type="button" class="realisation-preview-btn" data-preview-src="' . $thumbUrl . '" data-preview-type="image" data-preview-name="' . htmlspecialchars($name) . '">'
            . '<img src="' . $thumbUrl . '" alt="' . htmlspecialchars($name) . '" class="realisation-thumb" loading="lazy">'
            . '</button>'
            . '<a class="file-link" href="' . $assetUrl . '" download>' . htmlspecialchars($name) . '</a>'
            . '</div>';
    }
    if ($isVideo) {
        return '<div class="realisation-file">'
            . '<button type="button" class="realisation-preview-btn" data-preview-src="' . $assetUrl . '" data-preview-type="video" data-preview-name="' . htmlspecialchars($name) . '">'
            . '<span class="realisation-video-icon">▶</span>'
            . '</button>'
            . '<a class="file-link" href="' . $assetUrl . '" download>' . htmlspecialchars($name) . '</a>'
            . '</div>';
    }
    return '<div class="realisation-file"><a class="file-link" href="' . $assetUrl . '" download>' . htmlspecialchars($name) . '</a></div>';
}

function realisation_can_open_validation_task($taskType, $taskId, $canOpenValidationTaskLinks, array $currentUser = null): bool {
    if (!$canOpenValidationTaskLinks || (int) $taskId <= 0) {
        return false;
    }

    if (!UserScope::isScopedOperationalUser($currentUser)) {
        return true;
    }

    return UserScope::canAccessTaskType($currentUser, (string) $taskType);
}
?>

<section class="page-intro-card realisation-page-intro"><div><span class="page-eyebrow">Livrables et validations</span><h2>Réalisations</h2><p>Prévisualisez les contenus livrés, suivez les validations et partagez une sélection sécurisée avec le client.</p></div><span class="context-pill"><?= $total ?> livrable<?= $total > 1 ? 's' : '' ?></span></section>
<div class="entity-stats-grid realisation-stats"><article class="entity-stat"><span>Livrables trouvés</span><strong><?= $total ?></strong><small>Selon les filtres actifs</small></article><article class="entity-stat"><span>Clients disponibles</span><strong><?= count($clients) ?></strong><small>Portefeuille accessible</small></article><article class="entity-stat"><span>Liens générés</span><strong><?= count($generatedLinks) ?></strong><small>Dans cette opération</small></article></div><section class="panel js-ajax-list" data-list-scope="realisation">
    <div class="panel-head">
        <div>
            <h2>Bibliothèque des livrables</h2>
            <p class="panel-subtitle">Contenus avec fichiers livres — validation, liens client et telechargement.</p>
        </div>
    </div>

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
        <label class="field toolbar-field">
            <span>Validation interne</span>
            <select name="validation_interne">
                <?php foreach ($validationOptions as $v => $vl): ?>
                    <option value="<?= htmlspecialchars($v) ?>" <?= (($filters['validation_interne'] ?? '') === $v) ? 'selected' : '' ?>><?= htmlspecialchars($vl) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field toolbar-field">
            <span>Validation client</span>
            <select name="validation_client">
                <?php foreach ($validationOptions as $v => $vl): ?>
                    <option value="<?= htmlspecialchars($v) ?>" <?= (($filters['validation_client'] ?? '') === $v) ? 'selected' : '' ?>><?= htmlspecialchars($vl) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field toolbar-field">
            <span>Trier par</span>
            <select name="sort">
                <?php foreach ($sortOptions as $sortValue => $sortLabel): ?>
                    <option value="<?= htmlspecialchars($sortValue) ?>" <?= (($filters['sort'] ?? 'date_desc') === $sortValue) ? 'selected' : '' ?>><?= htmlspecialchars($sortLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="toolbar-actions">
            <button class="button" type="submit">Filtrer</button>
            <a class="button secondary" href="<?= htmlspecialchars(route_url('/realisation')) ?>">Reinitialiser</a>
        </div>
    </form>

    <?php if ($total > 0): ?>
        <p class="list-count"><?= $total ?> realisation<?= $total > 1 ? 's' : '' ?> · page <?= $page ?> / <?= $totalPages ?></p>
    <?php endif; ?>

    <form method="post" class="form-grid">
        <label class="field" style="max-width:220px;">
            <span>Expiration liens (jours)</span>
            <input type="number" name="expiry_days" value="45" min="1" max="365">
        </label>

        <div class="table-wrap compact-table">
            <table>
                <thead>
                    <tr>
                        <th></th>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Projet</th>
                        <th>Contenu</th>
                        <th>Type</th>
                        <th>Val. interne</th>
                        <th>Val. client</th>
                        <th>Note /10</th>
                        <th>Fichiers</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php $files = (array) ($item['files'] ?? []); ?>
                        <?php
                            $vi = (string) ($item['validation_interne_decision'] ?? '');
                            $viClass = $vi === 'Valide' ? 'status-ok' : ($vi === 'Non valide' ? 'status-ko' : 'status-pending');
                            $viTaskId = (int) ($item['validation_interne_task_id'] ?? 0);

                            $vc = (string) ($item['validation_client_decision'] ?? '');
                            $vcClass = $vc === 'Valide' ? 'status-ok' : ($vc === 'Non valide' ? 'status-ko' : 'status-pending');
                            $vcTaskId = (int) ($item['validation_client_task_id'] ?? $item['validation_task_id'] ?? 0);
                        ?>
                        <tr>
                            <td><input type="checkbox" name="deliverable_ids[]" value="<?= htmlspecialchars((string) ($item['deliverable_id'] ?? 0)) ?>"></td>
                            <td><?= htmlspecialchars((string) ($item['date_prevue'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($item['client_nom'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($item['projet_nom'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($item['titre'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($item['type_livrable'] ?? '')) ?></td>
                            <td><?php
                                $viLabel = htmlspecialchars($vi ?: 'En attente');
                                if (realisation_can_open_validation_task('Validation interne', $viTaskId, $canOpenValidationTaskLinks, $currentUser)) {
                                    echo '<a class="status-badge ' . $viClass . '" href="' . htmlspecialchars(route_url('/calendrier/task/' . $viTaskId)) . '">' . $viLabel . '</a>';
                                } else {
                                    echo '<span class="status-badge ' . $viClass . '">' . $viLabel . '</span>';
                                }
                            ?></td>
                            <td><?php
                                $vcLabel = htmlspecialchars($vc ?: 'En attente');
                                if (realisation_can_open_validation_task('Validation client', $vcTaskId, $canOpenValidationTaskLinks, $currentUser)) {
                                    echo '<a class="status-badge ' . $vcClass . '" href="' . htmlspecialchars(route_url('/calendrier/task/' . $vcTaskId)) . '">' . $vcLabel . '</a>';
                                } else {
                                    echo '<span class="status-badge ' . $vcClass . '">' . $vcLabel . '</span>';
                                }
                            ?></td>
                            <td><?= htmlspecialchars((string) (($item['note_sur_10'] ?? '') === '' ? 'N/A' : $item['note_sur_10'])) ?></td>
                            <td class="realisation-files-cell">
                                <?php foreach ($files as $file): ?>
                                    <?= realisation_file_preview($file) ?>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($items)): ?><tr><td colspan="10">Aucune realisation avec fichiers.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="form-actions">
            <button class="button" type="submit" name="action" value="generate_links">Generer lien(s) client</button>
            <button class="button secondary" type="submit" name="action" value="download_bundle">Telechargement de masse (ZIP)</button>
        </div>
    </form>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a class="button secondary" href="<?= htmlspecialchars(route_url('/realisation') . '?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">← Precedent</a>
            <?php endif; ?>
            <span class="pagination-info">Page <?= $page ?> / <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a class="button secondary" href="<?= htmlspecialchars(route_url('/realisation') . '?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">Suivant →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($generatedLinks)): ?>
        <div class="panel inset-panel" style="margin-top:16px;">
            <h3>Liens generes</h3>
            <ul class="requirement-list">
                <?php foreach ($generatedLinks as $link): ?>
                    <li class="requirement-item is-complete">
                        <span class="requirement-state"><?= htmlspecialchars((string) ($link['client_nom'] ?? 'Client')) ?></span>
                        <span><a class="link" href="<?= htmlspecialchars((string) ($link['url'] ?? '#')) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars((string) ($link['url'] ?? '')) ?></a> · <?= htmlspecialchars((string) ($link['deliverables_count'] ?? 0)) ?> contenu(x)</span>
                        <button type="button" class="button secondary" data-copy-link="<?= htmlspecialchars((string) ($link['url'] ?? '')) ?>">Copier le lien</button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</section>

<!-- Preview modal -->
<div id="realisation-modal" class="realisation-modal" hidden aria-modal="true" role="dialog">
    <div class="realisation-modal-backdrop"></div>
    <div class="realisation-modal-inner">
        <button class="realisation-modal-close" type="button" aria-label="Fermer">✕</button>
        <div class="realisation-modal-content" id="realisation-modal-content"></div>
    </div>
</div>

<style>
.realisation-file { display:flex; align-items:center; gap:6px; margin:2px 0; max-width:150px; }
.realisation-thumb { width:56px; height:56px; aspect-ratio:1 / 1; object-fit:cover; border-radius:6px; cursor:pointer; border:1px solid var(--line); transition:opacity .15s; }
.realisation-thumb:hover { opacity:.8; }
.realisation-preview-btn { background:none; border:none; padding:0; cursor:pointer; display:flex; align-items:center; }
.realisation-video-icon { display:flex; align-items:center; justify-content:center; width:56px; height:56px; aspect-ratio:1 / 1; border-radius:6px; background:var(--bg-panel); border:1px solid var(--line); font-size:20px; cursor:pointer; }
.realisation-files-cell { min-width:92px; width:92px; }
.realisation-file .file-link { display:block; max-width:84px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:.78em; }
.realisation-modal { position:fixed; inset:0; z-index:9999; display:flex; align-items:center; justify-content:center; }
.realisation-modal[hidden] { display:none; }
.realisation-modal-backdrop { position:absolute; inset:0; background:rgba(0,0,0,.6); }
.realisation-modal-inner { position:relative; z-index:1; background:var(--bg-panel); border-radius:10px; padding:20px; max-width:90vw; max-height:90vh; overflow:auto; box-shadow:0 8px 40px rgba(0,0,0,.4); }
.realisation-modal-close { position:absolute; top:10px; right:14px; background:none; border:none; font-size:20px; cursor:pointer; color:var(--ink); }
.realisation-modal-content img { max-width:80vw; max-height:75vh; border-radius:6px; display:block; }
.realisation-modal-content video { max-width:80vw; max-height:75vh; border-radius:6px; display:block; }
.status-badge { display:inline-block; padding:2px 8px; border-radius:12px; font-size:.78em; font-weight:600; white-space:nowrap; }
.status-ok { background:rgba(34,197,94,.15); color:#16a34a; }
.status-ko { background:rgba(239,68,68,.15); color:#dc2626; }
.status-pending { background:rgba(100,116,139,.12); color:var(--muted); }
.list-count { font-size:.82em; color:var(--muted); margin:6px 0 10px; }
.pagination { display:flex; align-items:center; gap:10px; padding:12px 0 4px; }
.pagination-info { font-size:.85em; color:var(--muted); }
</style>

<script>
(function () {
    var modal   = document.getElementById('realisation-modal');
    var content = document.getElementById('realisation-modal-content');
    if (!modal || !content) { return; }

    var backdrop = modal.querySelector('.realisation-modal-backdrop');
    var closeBtn = modal.querySelector('.realisation-modal-close');

    function openModal(src, type, name) {
        if (!src || !type) {
            return;
        }
        content.innerHTML = '';
        if (type === 'image') {
            var img = document.createElement('img');
            img.src = src;
            img.alt = name;
            content.appendChild(img);
        } else if (type === 'video') {
            var vid = document.createElement('video');
            vid.src = src;
            vid.controls = true;
            vid.autoplay = true;
            content.appendChild(vid);
        } else {
            return;
        }
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        content.innerHTML = '';
        document.body.style.overflow = '';
    }

    document.addEventListener('click', function (event) {
        var copyButton = event.target.closest('[data-copy-link]');
        if (copyButton) {
            var url = copyButton.getAttribute('data-copy-link') || '';
            if (!url) { return; }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    var original = copyButton.textContent;
                    copyButton.textContent = 'Copie !';
                    setTimeout(function () { copyButton.textContent = original; }, 1200);
                });
                return;
            }
            window.prompt('Copiez ce lien :', url);
            return;
        }

        var previewButton = event.target.closest('.realisation-preview-btn');
        if (previewButton) {
            openModal(
                previewButton.getAttribute('data-preview-src'),
                previewButton.getAttribute('data-preview-type'),
                previewButton.getAttribute('data-preview-name') || ''
            );
        }
    });

    if (backdrop) { backdrop.addEventListener('click', closeModal); }
    if (closeBtn) { closeBtn.addEventListener('click', closeModal); }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) { closeModal(); }
    });
})();
</script>

<script>
(function () {
    var section = document.querySelector('.js-ajax-list[data-list-scope="realisation"]');
    if (!section) { return; }

    function load(url, pushState) {
        if (!url) { return; }
        document.body.classList.add('is-loading');
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (response) { return response.text(); })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var nextSection = doc.querySelector('.js-ajax-list[data-list-scope="realisation"]');
                if (!nextSection) {
                    throw new Error('Liste introuvable');
                }
                section.innerHTML = nextSection.innerHTML;
                if (pushState !== false) {
                    window.history.pushState({ ajaxList: 'realisation' }, '', url);
                }
            })
            .catch(function () {
                window.location.href = url;
            })
            .finally(function () {
                document.body.classList.remove('is-loading');
            });
    }

    section.addEventListener('click', function (event) {
        var link = event.target.closest('.pagination a');
        if (!link) { return; }
        event.preventDefault();
        load(link.href, true);
    });

    section.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || !form.classList || !form.classList.contains('js-ajax-list-form')) {
            return;
        }
        event.preventDefault();
        var url = form.action || window.location.pathname;
        var query = new URLSearchParams(new FormData(form)).toString();
        load(url + (query ? ('?' + query) : ''), true);
    });

    window.addEventListener('popstate', function () {
        if ((window.location.pathname || '').indexOf('/realisation') !== -1) {
            load(window.location.href, false);
        }
    });
})();
</script>
