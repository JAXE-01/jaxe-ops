<?php
$project = $calendar['project'];
$plans = $calendar['plans'];
$availableMonths = $calendar['availableMonths'] ?? [];
$selectedMonth = $calendar['selectedMonth'] ?? null;
$selectedPlanId = (int) ($selectedPlanId ?? 0);
$readyDeliverablesForPublicValidation = is_array($readyDeliverablesForPublicValidation ?? null) ? $readyDeliverablesForPublicValidation : [];
$publicValidationLinks = is_array($publicValidationLinks ?? null) ? $publicValidationLinks : [];
$calendarStatsByPlan = is_array($calendarStatsByPlan ?? null) ? $calendarStatsByPlan : [];
$showAllPipelineStages = !empty($showAllPipelineStages);
$previousCalendarUrl = isset($previousCalendarUrl) ? (string) $previousCalendarUrl : '';
$nextCalendarUrl = isset($nextCalendarUrl) ? (string) $nextCalendarUrl : '';
$currentReturn = route_url('/calendrier/projet/' . $project['id']) . ($selectedMonth ? '?month=' . urlencode($selectedMonth) : '');

function calendrier_stage_columns($type) {
    if ($type === 'Video') {
        return ['Script', 'Tournage', 'Montage', 'Validation interne', 'Validation client', 'Publication', 'Collecte KPI'];
    }

    return ['Brief', 'Production', 'Validation interne', 'Validation client', 'Publication', 'Collecte KPI'];
}

function calendrier_visible_stage_columns($type, array $deliverables, $showAllPipelineStages) {
    $allColumns = calendrier_stage_columns($type);
    if ($showAllPipelineStages) {
        return $allColumns;
    }

    $visible = [];
    foreach ($deliverables as $deliverable) {
        foreach ((array) ($deliverable['tasks'] ?? []) as $task) {
            $taskType = (string) ($task['type_tache'] ?? '');
            if (in_array($taskType, $allColumns, true) && !in_array($taskType, $visible, true)) {
                $visible[] = $taskType;
            }
        }
    }

    return $visible;
}

function calendrier_group_deliverables(array $deliverables) {
    $grouped = ['Video' => [], 'Visuel' => []];
    foreach ($deliverables as $deliverable) {
        $grouped[$deliverable['type_livrable']][] = $deliverable;
    }
    return $grouped;
}

function calendrier_preview_label($file) {
    $extension = strtoupper((string) ($file['extension'] ?? ''));
    return $extension !== '' ? $extension : 'FICHIER';
}

function calendrier_preview_role_label($file) {
    return (string) ($file['role_label'] ?? 'EXPORT');
}

function calendrier_task_status_class(array $task) {
    $status = (string) ($task['statut'] ?? '');
    return strtolower(str_replace(' ', '-', $status));
}

function calendrier_task_status_label(array $task) {
    $status = (string) ($task['statut'] ?? '');
    if (in_array((string) ($task['type_tache'] ?? ''), ['Montage', 'Production', 'Brief', 'Script', 'Calendrier'], true)
        && $status === 'Annulee') {
        return 'Non valide';
    }
    return $status;
}
?>
<section class="panel">
    <div class="panel-head">
        <div>
            <h2><?= htmlspecialchars($project['entreprise']) ?> · <?= htmlspecialchars($project['nom']) ?></h2>
            <p class="panel-subtitle"><?= htmlspecialchars($project['type_projet']) ?> · <?= htmlspecialchars($project['date_debut']) ?> → <?= htmlspecialchars($project['date_fin']) ?> · Canal principal: <?= htmlspecialchars($project['canal_principal'] ?: 'N/A') ?></p>
        </div>
        <div class="toolbar-actions">
            <?php if ($previousCalendarUrl !== ''): ?>
                <a class="button secondary" href="<?= htmlspecialchars($previousCalendarUrl) ?>" data-calendar-async-link>← Calendrier precedent</a>
            <?php endif; ?>
            <?php if ($nextCalendarUrl !== ''): ?>
                <a class="button secondary" href="<?= htmlspecialchars($nextCalendarUrl) ?>" data-calendar-async-link>Calendrier suivant →</a>
            <?php endif; ?>
            <a class="button secondary" href="<?= htmlspecialchars(route_url('/projet/edit/' . $project['id']) . '?return_to=' . urlencode($currentReturn)) ?>">Modifier le projet</a>
            <a class="button secondary" href="<?= htmlspecialchars(route_url('/projet/extend/' . $project['id']) . '?return_to=' . urlencode($currentReturn)) ?>">Prolonger d un mois</a>
            <a class="button secondary" href="<?= htmlspecialchars(route_url('/projet/regenerate/' . $project['id']) . '?return_to=' . urlencode($currentReturn)) ?>">Resynchroniser le pipeline</a>
            <a class="button secondary" href="<?= htmlspecialchars(route_url('/calendrier/publicationCalendar/' . $project['id']) . ($selectedMonth ? '?month=' . urlencode($selectedMonth) : '')) ?>">Calendrier publication</a>
            <a class="button secondary" href="<?= htmlspecialchars(route_url('/calendrier/client/' . $project['client_id'])) ?>">Retour au client</a>
        </div>
    </div>
    <div class="chips-row">
        <span class="chip">Charge de communication: <?= htmlspecialchars($project['charge_compte_nom'] ?: 'Non assigne') ?></span>
        <span class="chip">Charge de clientele: <?= htmlspecialchars($project['charge_clientele_nom'] ?: 'Non assigne') ?></span>
        <span class="chip">CM: <?= htmlspecialchars($project['cm_nom'] ?: 'Non assigne') ?></span>
        <span class="chip">Createur: <?= htmlspecialchars($project['createur_nom'] ?: 'Non assigne') ?></span>
        <span class="chip">Cadreur: <?= htmlspecialchars($project['cadreur_nom'] ?: 'Non assigne') ?></span>
        <span class="chip">Videaste: <?= htmlspecialchars($project['videaste_nom'] ?: 'Non assigne') ?></span>
        <span class="chip">Designer: <?= htmlspecialchars($project['designer_nom'] ?: 'Non assigne') ?></span>
        <span class="chip">Quota: <?= htmlspecialchars((string) $project['quota_videos_mensuel']) ?> video(s) / <?= htmlspecialchars((string) $project['quota_visuels_mensuel']) ?> visuel(x)</span>
    </div>
    <?php if (!empty($availableMonths)): ?>
        <form method="get" action="<?= htmlspecialchars(route_url('/calendrier/projet/' . $project['id'])) ?>" class="list-toolbar" data-calendar-async-form>
            <label class="field toolbar-field">
                <span>Mois</span>
                <select name="month">
                    <option value="">Tous les mois</option>
                    <?php foreach ($availableMonths as $month): ?>
                        <option value="<?= htmlspecialchars($month['value']) ?>" <?= $selectedMonth === $month['value'] ? 'selected' : '' ?>><?= htmlspecialchars($month['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="toolbar-actions">
                <button class="button" type="submit">Filtrer</button>
                <a class="button secondary" href="<?= htmlspecialchars(route_url('/calendrier/projet/' . $project['id'])) ?>" data-calendar-async-link>Voir tout</a>
            </div>
        </form>
    <?php endif; ?>
</section>

<div id="project-calendar-board" data-calendar-board>
    <?php require __DIR__ . '/projet_board.php'; ?>
</div>

<div class="preview-modal" id="calendar-preview-modal" hidden>
    <div class="preview-modal-backdrop" data-preview-close></div>
    <div class="preview-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="calendar-preview-title">
        <button class="preview-modal-close" type="button" aria-label="Fermer" data-preview-close>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <div class="preview-modal-head">
            <strong id="calendar-preview-title">Apercu</strong>
        </div>
        <div class="preview-modal-body" id="calendar-preview-body"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var board = document.querySelector('[data-calendar-board]');
    var monthForm = document.querySelector('[data-calendar-async-form]');
    var boardCache = Object.create(null);
    var modal = document.getElementById('calendar-preview-modal');
    var body = document.getElementById('calendar-preview-body');
    var title = document.getElementById('calendar-preview-title');
    if (!modal || !body || !title) {
        return;
    }

    function closePreview() {
        modal.hidden = true;
        body.innerHTML = '';
        document.body.classList.remove('preview-open');
    }

    function openPreview(link) {
        var kind = link.getAttribute('data-preview-kind');
        var src = link.getAttribute('data-preview-src');
        var name = link.getAttribute('data-preview-name') || 'Apercu';
        if (!kind || !src) {
            return;
        }

        title.textContent = name;
        body.innerHTML = '';

        if (kind === 'image') {
            var image = document.createElement('img');
            image.src = src;
            image.alt = name;
            image.className = 'preview-modal-image';
            body.appendChild(image);
        }

        if (kind === 'video') {
            var video = document.createElement('video');
            video.src = src;
            video.controls = true;
            video.autoplay = true;
            video.playsInline = true;
            video.className = 'preview-modal-video';
            body.appendChild(video);
        }

        modal.hidden = false;
        document.body.classList.add('preview-open');
    }

    document.addEventListener('click', function (event) {
        var previewLink = event.target.closest('[data-preview-kind]');
        if (previewLink) {
            event.preventDefault();
            openPreview(previewLink);
            return;
        }

        if (event.target.closest('[data-preview-close]')) {
            closePreview();
            return;
        }

        var copyButton = event.target.closest('[data-copy-link]');
        if (copyButton) {
            var url = copyButton.getAttribute('data-copy-link') || '';
            if (!url) {
                return;
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    copyButton.textContent = 'Copie';
                    setTimeout(function () {
                        copyButton.textContent = 'Copier le lien';
                    }, 1200);
                });
                return;
            }

            window.prompt('Copiez ce lien:', url);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) {
            closePreview();
        }
    });

    function buildFragmentUrl(rawUrl) {
        var target = new URL(rawUrl, window.location.origin);
        target.searchParams.set('fragment', 'board');
        return target.toString();
    }

    function skeletonMarkup() {
        return ''
            + '<div class="calendar-skeleton">'
            + '  <div class="calendar-skeleton-row"></div>'
            + '  <div class="calendar-skeleton-row short"></div>'
            + '  <div class="calendar-skeleton-grid">'
            + '    <span></span><span></span><span></span><span></span>'
            + '  </div>'
            + '</div>';
    }

    function loadBoard(rawUrl, pushState) {
        if (!board || !rawUrl) {
            return;
        }

        var cacheKey = buildFragmentUrl(rawUrl);
        if (boardCache[cacheKey]) {
            board.innerHTML = boardCache[cacheKey];
            if (pushState !== false) {
                window.history.pushState({ calendarBoard: true }, '', rawUrl);
            }
            return;
        }

        document.body.classList.add('is-loading');
        board.classList.add('is-skeleton');
        board.innerHTML = skeletonMarkup();

        fetch(cacheKey, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            },
            credentials: 'same-origin'
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('chargement');
            }
            return response.text();
        }).then(function (html) {
            boardCache[cacheKey] = html;
            board.innerHTML = html;
            if (pushState !== false) {
                window.history.pushState({ calendarBoard: true }, '', rawUrl);
            }
        }).catch(function () {
            window.location.href = rawUrl;
        }).finally(function () {
            document.body.classList.remove('is-loading');
            board.classList.remove('is-skeleton');
        });
    }

    document.addEventListener('click', function (event) {
        var asyncLink = event.target.closest('[data-calendar-async-link]');
        if (!asyncLink) {
            return;
        }
        event.preventDefault();
        loadBoard(asyncLink.href, true);
    });

    if (monthForm) {
        monthForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var query = new URLSearchParams(new FormData(monthForm)).toString();
            var targetUrl = monthForm.getAttribute('action') + (query ? ('?' + query) : '');
            loadBoard(targetUrl, true);
        });
    }

    window.addEventListener('popstate', function () {
        loadBoard(window.location.href, false);
    });
});
</script>
