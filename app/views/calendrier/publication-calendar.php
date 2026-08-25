<?php
$eventsByDate = is_array($eventsByDate ?? null) ? $eventsByDate : [];
$itemsByDate = is_array($itemsByDate ?? null) ? $itemsByDate : [];
$canManageCalendar = !empty($canManageCalendar);
$projectBoardUrl = (string) ($projectBoardUrl ?? route_url('/calendrier'));
$publicationCalendarAction = route_url('/calendrier/publicationCalendar/' . (int) ($project['id'] ?? 0))
    . (!empty($plan['periode_mois']) ? '?month=' . urlencode((string) $plan['periode_mois']) : '');

$monthStart = new DateTime(date('Y-m-01', strtotime((string) ($plan['periode_mois'] ?? date('Y-m-01')))));
$monthEnd = new DateTime(date('Y-m-t', strtotime((string) ($plan['periode_mois'] ?? date('Y-m-01')))));
$calendarStart = clone $monthStart;
$calendarStart->modify('monday this week');
$calendarEnd = clone $monthEnd;
$calendarEnd->modify('sunday this week');

$weekdayLabels = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

function publication_board_row_url($baseUrl, $deliverableId) {
    $separator = strpos($baseUrl, '?') === false ? '?' : '&';
    return $baseUrl . $separator . 'focus_deliverable=' . (int) $deliverableId . '#deliverable-' . (int) $deliverableId;
}
?>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Calendrier publication · <?= htmlspecialchars((string) ($project['nom'] ?? 'Projet')) ?></h2>
            <p class="panel-subtitle">Mois: <?= htmlspecialchars(date('F Y', strtotime((string) ($plan['periode_mois'] ?? date('Y-m-01'))))) ?></p>
        </div>
        <a class="button secondary" href="<?= htmlspecialchars((string) ($returnTo ?? route_url('/calendrier'))) ?>">Retour au projet</a>
    </div>

    <div class="publication-weekdays">
        <?php foreach ($weekdayLabels as $weekday): ?>
            <div><?= htmlspecialchars($weekday) ?></div>
        <?php endforeach; ?>
    </div>

    <div class="publication-month-grid">
        <?php
        $cursor = clone $calendarStart;
        while ($cursor <= $calendarEnd):
            $dateKey = $cursor->format('Y-m-d');
            $event = $eventsByDate[$dateKey] ?? null;
            $inCurrentMonth = $cursor->format('Y-m') === $monthStart->format('Y-m');
        ?>
            <button type="button" class="publication-day-card<?= $inCurrentMonth ? '' : ' is-outside-month' ?>" data-day-open="<?= htmlspecialchars($dateKey) ?>">
                <span class="detail-label"><?= htmlspecialchars($cursor->format('d/m/Y')) ?></span>
                <?php if ($event): ?>
                    <span class="status-badge status-en-cours"><?= htmlspecialchars((string) ($event['total'] ?? 0)) ?> contenu(x)</span>
                    <span class="chips-row">
                        <?php foreach (($event['channels'] ?? []) as $channel => $count): ?>
                            <span class="chip"><?= htmlspecialchars((string) $channel) ?> · <?= htmlspecialchars((string) $count) ?></span>
                        <?php endforeach; ?>
                    </span>
                <?php else: ?>
                    <span class="mini-text">Aucun contenu planifie</span>
                <?php endif; ?>
            </button>
        <?php
            $cursor->modify('+1 day');
        endwhile;
        ?>
    </div>
</section>

<div id="publication-day-modal" class="publication-day-modal" hidden>
    <div class="publication-day-backdrop" data-day-close="1"></div>
    <div class="publication-day-dialog" role="dialog" aria-modal="true" aria-labelledby="publication-day-title">
        <button type="button" class="publication-day-close" data-day-close="1" aria-label="Fermer">×</button>
        <h3 id="publication-day-title">Contenus du jour</h3>
        <div id="publication-day-content"></div>
    </div>
</div>

<template id="publication-day-empty-template">
    <p class="mini-text">Aucun contenu planifie sur cette date.</p>
</template>

<?php
$cursor = clone $calendarStart;
while ($cursor <= $calendarEnd):
    $dateKey = $cursor->format('Y-m-d');
    $items = (array) ($itemsByDate[$dateKey] ?? []);
?>
    <template id="publication-day-template-<?= htmlspecialchars($dateKey) ?>">
        <div class="panel inset-panel">
            <div class="panel-head">
                <div>
                    <h3><?= htmlspecialchars($cursor->format('d/m/Y')) ?></h3>
                    <p class="panel-subtitle"><?= htmlspecialchars((string) count($items)) ?> contenu(x)</p>
                </div>
            </div>
            <?php if (empty($items)): ?>
                <p class="mini-text">Aucun contenu planifie sur cette date.</p>
            <?php else: ?>
                <div class="file-list">
                    <?php foreach ($items as $item): ?>
                        <article class="detail-card">
                            <span class="detail-label"><?= htmlspecialchars((string) ($item['type_livrable'] ?? 'Contenu')) ?> · <?= htmlspecialchars((string) ($item['canal'] ?? 'Canal')) ?></span>
                            <div class="detail-value"><?= htmlspecialchars((string) ($item['titre'] ?? 'Contenu')) ?></div>
                            <div class="chips-row">
                                <a class="button secondary" href="<?= htmlspecialchars(publication_board_row_url($projectBoardUrl, (int) ($item['deliverable_id'] ?? 0))) ?>">Voir dans le calendrier principal</a>
                            </div>
                            <?php if ($canManageCalendar): ?>
                                <form method="post" action="<?= htmlspecialchars($publicationCalendarAction) ?>" class="mini-inline-form" style="margin-top:8px;">
                                    <input type="hidden" name="manager_action" value="move_publication_date">
                                    <input type="hidden" name="deliverable_id" value="<?= htmlspecialchars((string) ($item['deliverable_id'] ?? 0)) ?>">
                                    <label class="field" style="margin:0;">
                                        <span>Nouvelle date</span>
                                        <input type="date" name="new_date_prevue" value="<?= htmlspecialchars((string) ($item['date_prevue'] ?? '')) ?>" required>
                                    </label>
                                    <button class="button" type="submit">Mettre a jour</button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </template>
<?php
    $cursor->modify('+1 day');
endwhile;
?>

<style>
.publication-weekdays {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 10px;
}

.publication-weekdays div {
    font-size: 0.82rem;
    color: var(--muted);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.publication-month-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 10px;
}

.publication-day-card {
    appearance: none;
    border: 1px solid var(--line);
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.84);
    padding: 10px;
    text-align: left;
    min-height: 120px;
    display: grid;
    gap: 8px;
    cursor: pointer;
}

.publication-day-card.is-outside-month {
    opacity: 0.5;
}

.publication-day-card:hover {
    border-color: rgba(63, 120, 168, 0.35);
}

.publication-day-modal[hidden] {
    display: none;
}

.publication-day-modal {
    position: fixed;
    inset: 0;
    z-index: 1200;
    display: flex;
    align-items: center;
    justify-content: center;
}

.publication-day-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(17, 24, 39, 0.52);
}

.publication-day-dialog {
    position: relative;
    z-index: 1;
    width: min(940px, 92vw);
    max-height: 88vh;
    overflow: auto;
    background: var(--bg-panel);
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 18px;
}

.publication-day-close {
    position: absolute;
    right: 10px;
    top: 8px;
    border: 0;
    background: transparent;
    font-size: 1.6rem;
    line-height: 1;
    cursor: pointer;
    color: var(--muted);
}
</style>

<script>
(function () {
    var modal = document.getElementById('publication-day-modal');
    var contentNode = document.getElementById('publication-day-content');
    var titleNode = document.getElementById('publication-day-title');
    if (!modal || !contentNode || !titleNode) {
        return;
    }

    function openDay(dateKey) {
        var template = document.getElementById('publication-day-template-' + dateKey);
        var fallback = document.getElementById('publication-day-empty-template');
        contentNode.innerHTML = '';
        if (template && template.content) {
            contentNode.appendChild(template.content.cloneNode(true));
        } else if (fallback && fallback.content) {
            contentNode.appendChild(fallback.content.cloneNode(true));
        }
        titleNode.textContent = 'Contenus du ' + dateKey.split('-').reverse().join('/');
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.hidden = true;
        document.body.style.overflow = '';
        contentNode.innerHTML = '';
    }

    document.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-day-open]');
        if (opener) {
            openDay(opener.getAttribute('data-day-open'));
            return;
        }

        if (event.target.closest('[data-day-close="1"]')) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });

    // AJAX date modification
    document.addEventListener('submit', function (evt) {
        var form = evt.target;
        if (!form || !form.closest('#publication-day-content')) { return; }
        if (form.getAttribute('data-skip-ajax') === '1') { return; }
        var managerAction = (form.querySelector('[name="manager_action"]') || {}).value || '';
        if (managerAction !== 'move_publication_date') { return; }
        evt.preventDefault();

        var actionUrl = form.getAttribute('action') || window.location.href;
        var dateInput = form.querySelector('[name="new_date_prevue"]');
        var btn = form.querySelector('button[type="submit"]');
        var newDate = dateInput ? dateInput.value : '';

        if (!newDate) {
            if (window.AppUI && window.AppUI.toast) {
                window.AppUI.toast('error', 'Selectionnez une date valide.');
            }
            return;
        }

        if (btn) { btn.disabled = true; btn.textContent = '...'; }

        var fallbackSubmit = function () {
            form.setAttribute('data-skip-ajax', '1');
            form.submit();
        };

        var fd = new FormData(form);
        fetch(actionUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: fd
        }).then(function (r) {
            return r.text().then(function (text) {
                var json = null;
                try {
                    json = text ? JSON.parse(text) : null;
                } catch (e) {
                    json = null;
                }
                return { ok: r.ok, json: json };
            });
        }).then(function (result) {
            if (btn) { btn.disabled = false; btn.textContent = 'Mettre a jour'; }
            if (!result.json) {
                fallbackSubmit();
                return;
            }
            if (result.ok && result.json && result.json.ok) {
                if (window.AppUI && window.AppUI.toast) {
                    window.AppUI.toast('success', 'Date mise a jour.');
                }
                // Update date input displayed value
                if (dateInput) { dateInput.defaultValue = newDate; }
                // Update the day button label in the grid
                var deliverableId = (form.querySelector('[name="deliverable_id"]') || {}).value || '';
                var dayBtn = document.querySelector('[data-day-open]');
                // Reload page after short delay to reflect new calendar layout
                setTimeout(function () { window.location.reload(); }, 800);
            } else {
                var msg = (result.json && result.json.message) || 'Erreur lors de la mise a jour.';
                if (window.AppUI && window.AppUI.toast) {
                    window.AppUI.toast('error', msg);
                }
            }
        }).catch(function () {
            if (btn) { btn.disabled = false; btn.textContent = 'Mettre a jour'; }
            fallbackSubmit();
        });
    });
})();
</script>
