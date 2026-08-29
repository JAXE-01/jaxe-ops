<?php
$deliverable = $workspace;
$campaignId = (int) ($deliverable['campagne_id'] ?? 0);
$personaId = (int) ($_POST['persona_id'] ?? $deliverable['persona_id'] ?? 0);
$canEdit = !empty($canEditContentSetup);
$canManagerInvalidate = !empty($canManagerInvalidate);
$monthStart = (string) ($monthStart ?? '');
$monthEnd = (string) ($monthEnd ?? '');
$scheduledPublicationDates = is_array($scheduledPublicationDates ?? null) ? $scheduledPublicationDates : [];
$invalidationHistory = is_array($deliverable['invalidation_history'] ?? null) ? $deliverable['invalidation_history'] : [];
$contentObjectiveOptions = array_values(array_filter((array) ($contentObjectiveOptions ?? [])));
$selectedObjective = trim((string) ($_POST['objectif_publication'] ?? $deliverable['objectif_publication'] ?? ''));
if ($selectedObjective !== '' && !in_array($selectedObjective, $contentObjectiveOptions, true)) {
    $contentObjectiveOptions[] = $selectedObjective;
}
$contentRequirements = ContentCompletion::requirements($deliverable);
$compositionContext=ContentMatrixReferences::load((int)$deliverable['client_id'],(int)($_GET['composition_matrix_id']??0));
$tpackRefs=$compositionContext['refs'];
foreach(['tpack_target'=>'targets','tpack_objective'=>'objectives','tpack_problem'=>'problems','tpack_product'=>'products','tpack_format'=>'formats','tpack_cta'=>'ctas','tpack_platform'=>'platforms'] as $field=>$key){
 $saved=trim((string)($_POST[$field]??$deliverable[$field]??''));
 if($saved!==''&&!in_array($saved,$tpackRefs[$key],true))$tpackRefs[$key][]=$saved;
}
?>
<link rel="stylesheet" href="<?= htmlspecialchars(app_url('/public/assets/content-compact.css')) ?>"><link rel="stylesheet" href="<?= htmlspecialchars(app_url('/public/assets/content-density.css')) ?>"><script src="<?= htmlspecialchars(app_url('/public/assets/content-date-picker.js')) ?>" defer></script><section class="panel content-workspace-hero">
    <div class="panel-head">
        <div>
            <h2>Fiche contenu</h2>
            <p class="panel-subtitle"><?= htmlspecialchars($deliverable['client_nom']) ?> · <?= htmlspecialchars($deliverable['projet_nom']) ?> · <?= htmlspecialchars($deliverable['titre']) ?></p>
        </div>
        <div class="toolbar-actions">
            <?php if (!empty($previousContentUrl)): ?>
                <a class="button secondary" href="<?= htmlspecialchars((string) $previousContentUrl) ?>" data-shortcut-prev title="Contenu précédent" aria-label="Contenu précédent">←</a>
            <?php endif; ?>
            <?php if (!empty($nextContentUrl)): ?>
                <a class="button secondary" href="<?= htmlspecialchars((string) $nextContentUrl) ?>" data-shortcut-next title="Contenu suivant" aria-label="Contenu suivant">→</a>
            <?php endif; ?>
            <button class="button secondary" type="button" data-compact-toggle data-icon-toggle title="Changer la densité" aria-label="Changer la densité">▤</button>
            <?php if (!empty($briefEditUrl)): ?>
                <a class="button secondary" href="#inline-content-brief" data-open-inline-brief title="Voir ou modifier le script / brief" aria-label="Voir ou modifier le script / brief">✎</a>
            <?php endif; ?>
            <a class="button secondary" href="<?= htmlspecialchars($returnTo) ?>" title="Retour au projet" aria-label="Retour au projet">↩</a>
        </div>
    </div>

    <div class="detail-grid">
        <article class="detail-card"><span class="detail-label">Type</span><div class="detail-value"><?= htmlspecialchars($deliverable['type_livrable']) ?><?= !empty($deliverable['sous_type']) ? ' · ' . htmlspecialchars($deliverable['sous_type']) : '' ?></div></article>
        <article class="detail-card"><span class="detail-label">Reseau principal</span><div class="detail-value"><?= htmlspecialchars($deliverable['canal'] ?: $deliverable['canal_principal'] ?: 'N/A') ?></div></article>
        <article class="detail-card"><span class="detail-label">Statut de la fiche</span><div class="detail-value"><?= !empty($deliverable['content_ready']) ? 'Pret pour brief/script' : 'Informations a completer' ?></div></article>
        <article class="detail-card"><span class="detail-label">Campagne</span><div class="detail-value"><?= $campaignId > 0 ? htmlspecialchars($deliverable['campagne_nom'] ?: ('#' . $campaignId)) : 'Aucune campagne liee' ?></div></article>
    </div>
</section>

<?php require __DIR__.'/content-overview.php'; require __DIR__.'/matrix-selector.php'; ?>
<section class="panel content-history-panel">
    <div class="panel-head">
        <div>
            <h2>Historique des invalidations</h2>
            <p class="panel-subtitle">Commentaires recus lors des validations interne et client.</p>
        </div>
    </div>
    <?php if (empty($invalidationHistory)): ?>
        <p class="mini-text">Aucune invalidation enregistree pour ce contenu.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($invalidationHistory as $entry): ?>
                <?php
                    $rawSource = (string) ($entry['validation_type'] ?: $entry['source'] ?: 'Validation');
                    if ($rawSource === 'public') {
                        $rawSource = 'Validation client';
                    } elseif ($rawSource === 'internal') {
                        $rawSource = 'Validation interne';
                    }
                    $rawDate = trim((string) ($entry['created_at'] ?? ''));
                    $dateLabel = $rawDate !== '' ? date('d/m/Y H:i', strtotime($rawDate)) : '';
                    $commentLabel = trim((string) ($entry['comment'] ?? ''));
                ?>
                <li>
                    <strong><?= htmlspecialchars($rawSource) ?></strong>
                    <?php if ($dateLabel !== ''): ?> · <span class="mini-text"><?= htmlspecialchars($dateLabel) ?></span><?php endif; ?>
                    <div><?= htmlspecialchars($commentLabel !== '' ? $commentLabel : 'Commentaire non renseigne.') ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<details class="panel collapsible-panel content-general-panel" open>
    <summary class="collapsible-summary">
        <span>
            <strong>Informations generales du mois</strong>
            <small>Contexte commun et elements de base</small>
        </span>
        <span class="collapsible-indicator">Afficher / masquer</span>
    </summary>
    <div class="panel-head">
        <div>
            <h2>Informations generales du mois</h2>
            <p class="panel-subtitle">Ces informations servent de contexte commun a tous les contenus du mois.</p>
        </div>
    </div>



    <form method="post" class="form-grid" data-autosave-form="true" data-autosave-label="Fiche contenu" data-autosave-endpoint="<?= htmlspecialchars(route_url('/calendrier/contenu/' . (int) ($deliverable['id'] ?? 0))) ?>">
        <div class="autosave-status" data-autosave-status>Modifications locales</div>
        <label class="field">
            <span>Dates ou evenements cles du mois</span>
            <textarea name="temps_forts_mois" <?= !$canEdit ? 'disabled' : '' ?>><?= htmlspecialchars((string) ($_POST['temps_forts_mois'] ?? $deliverable['temps_forts_mois'] ?? '')) ?></textarea>
        </label>
        <label class="field">
            <span>Contexte du mois / cadre general</span>
            <textarea name="contexte_mois" <?= !$canEdit ? 'disabled' : '' ?>><?= htmlspecialchars((string) ($_POST['contexte_mois'] ?? $deliverable['contexte_mois'] ?? '')) ?></textarea>
        </label>
        <label class="field">
            <span>Objectif du mois</span>
            <textarea name="objectif_mois" <?= !$canEdit ? 'disabled' : '' ?>><?= htmlspecialchars((string) ($_POST['objectif_mois'] ?? $deliverable['objectif_mois'] ?? '')) ?></textarea>
        </label>

        <details class="panel inset-panel tpack-composer-panel" open>
            <summary class="collapsible-summary"><span><strong>Composition à partir de la matrice client</strong><small>Combinez cible, objectif, besoin, produit, format et appel à l’action.</small></span><span class="collapsible-indicator">Afficher / masquer</span></summary>
            <input type="hidden" name="composition_method" value="TPACK">
            <div class="tpack-grid">
                <?php foreach ([['tpack_target','Cible','targets'],['tpack_objective','Objectif','objectives'],['tpack_problem','Problème / besoin','problems'],['tpack_product','Produit / offre','products'],['tpack_format','Format','formats'],['tpack_cta','Appel à l action','ctas'],['tpack_platform','Plateforme','platforms']] as $definition): ?>
                <label class="field"><span><?= htmlspecialchars($definition[1]) ?></span><select name="<?= $definition[0] ?>" data-tpack-input><option value="">Sélectionner</option><?php $selectedTpack=(string)($_POST[$definition[0]]??$deliverable[$definition[0]]??''); foreach($tpackRefs[$definition[2]] as $option): ?><option value="<?= htmlspecialchars($option) ?>" <?= $selectedTpack===$option?'selected':'' ?>><?= htmlspecialchars($option) ?></option><?php endforeach; ?></select></label>
                <?php endforeach; ?>
                <label class="field tpack-hook-field"><span>Accroche / idée</span><input type="text" name="tpack_hook" value="<?= htmlspecialchars((string)($_POST['tpack_hook']??$deliverable['tpack_hook']??'')) ?>" placeholder="Accroche du contenu"></label>
                <label class="field"><span>Priorité</span><select name="tpack_priority"><option>Haute</option><option <?= (($_POST['tpack_priority']??$deliverable['tpack_priority']??'Moyenne')==='Moyenne')?'selected':'' ?>>Moyenne</option><option>Basse</option></select></label>
                <label class="field"><span>Statut de l idée</span><select name="tpack_status"><option>À discuter</option><option>Retenue</option><option>Planifiée</option><option>Écartée</option></select></label>
            </div>
            <label class="field tpack-brief-field"><span>Combinaison générée</span><textarea name="tpack_generated_brief" data-tpack-output><?= htmlspecialchars((string)($_POST['tpack_generated_brief']??$deliverable['tpack_generated_brief']??'')) ?></textarea></label>
            <div class="form-actions"><button class="button secondary" type="button" data-tpack-generate>Générer la combinaison</button><button class="button primary" type="button" data-tpack-apply>Appliquer à la fiche contenu</button></div>
        </details>
        <div class="panel inset-panel content-specific-panel">
            <div class="panel-head">
                <div>
                    <h2>Informations specifiques du contenu</h2>
                    <p class="panel-subtitle">Préparez votre idée et son message. Les validations restent distinctes de la sauvegarde.</p>
                </div>
            </div>

            <div class="form-grid">
                <label class="field">
                    <span>Sujet / angle éditorial</span><small class="field-help">Angle à traiter, distinct du nom du livrable affiché en haut.</small>
                    <textarea name="sujet" <?= !$canEdit ? 'disabled' : '' ?>><?= htmlspecialchars((string) ($_POST['sujet'] ?? $deliverable['contenu_sujet'] ?? $deliverable['titre'] ?? '')) ?></textarea>
                </label>
                <label class="field">
                    <span>Objectif de cette publication</span>
                    <select name="objectif_publication" <?= !$canEdit ? 'disabled' : '' ?>>
                        <option value="">Selectionner un objectif</option>
                        <?php foreach ($contentObjectiveOptions as $option): ?>
                            <option value="<?= htmlspecialchars($option) ?>" <?= $selectedObjective === $option ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="content-schedule-stack">
                    <label class="field">
                        <span>Date de publication prevue</span>
                        <input type="date" name="date_prevue" value="<?= htmlspecialchars((string) ($_POST['date_prevue'] ?? $deliverable['date_prevue'] ?? '')) ?>" <?= !$canEdit ? 'disabled' : '' ?>>
                        <small class="field-help">Planification possible au-dela du mois courant.</small>
                        <?php require __DIR__.'/date-occupancy-calendar.php'; ?>
                    </label>
                    <label class="field">
                        <span>Responsable editorial</span>
                        <?php $selectedEditorialOwner = (string) ($_POST['responsable'] ?? $deliverable['contenu_responsable'] ?? ''); ?>
                        <select name="responsable" <?= !$canEdit ? 'disabled' : '' ?>>
                            <option value="">Non assigne</option>
                            <?php foreach (($editorialUserOptions ?? []) as $optionValue => $optionLabel): ?>
                                <option value="<?= htmlspecialchars((string) $optionValue) ?>" <?= $selectedEditorialOwner === (string) $optionValue ? 'selected' : '' ?>><?= htmlspecialchars((string) $optionLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <label class="field">
                    <span>Persona cible</span>
                    <select name="persona_id" <?= !$canEdit ? 'disabled' : '' ?>>
                        <option value="">Aucun persona selectionne</option>
                        <?php foreach ($personaOptions as $optionValue => $label): ?>
                            <option value="<?= htmlspecialchars((string) $optionValue) ?>" <?= $personaId === (int) $optionValue ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php require __DIR__.'/persona-summary.php'; ?>
                <label class="field">
                    <span>Cible libre (alternative au persona)</span>
                    <textarea name="cible_libre" <?= !$canEdit ? 'disabled' : '' ?>><?= htmlspecialchars((string) ($_POST['cible_libre'] ?? $deliverable['cible_libre'] ?? '')) ?></textarea>
                </label>
                <label class="field">
                    <span>Reseau principalement cible</span>
                    <?php require __DIR__.'/network-select.php'; ?>
                </label>
                <label class="field">
                    <span>Message a vehiculer</span>
                    <textarea name="message" <?= !$canEdit ? 'disabled' : '' ?>><?= htmlspecialchars((string) ($_POST['message'] ?? $deliverable['contenu_message'] ?? '')) ?></textarea>
                </label>
            </div>
        </div>


        <?php if ($canEdit || $canManagerInvalidate): ?>
            <div class="form-actions">
                <?php if ($canEdit): ?>
                    <button class="button" type="submit">Enregistrer le brouillon</button>
                <?php endif; ?>
                <?php if ($canManagerInvalidate): ?>
                    <button class="button secondary" type="submit" name="manager_action" value="invalidate_content" onclick="return confirm('Marquer cette fiche contenu comme non valide ?');">Invalider la fiche</button>
                <?php endif; ?>
                <a class="button secondary" href="<?= htmlspecialchars($returnTo) ?>" title="Retour au projet" aria-label="Retour au projet">↩</a>
            </div>
        <?php endif; ?>
    </form>
</details>

<?php require __DIR__.'/inline-brief.php'; ?>

<script>
(function () {
    var form = document.querySelector('form[data-autosave-form="true"]');
    if (!form) { return; }

    var statusNode = form.querySelector('[data-autosave-status]');
    var timer = null;
    var inFlight = false;
    var dirty = false;

    function setStatus(text, state) {
        if (!statusNode) { return; }
        statusNode.textContent = text;
        statusNode.setAttribute('data-state', state || 'idle');
    }

    function hasSelectedFiles() {
        var fileInputs = form.querySelectorAll('input[type="file"]');
        for (var i = 0; i < fileInputs.length; i++) {
            if (fileInputs[i].files && fileInputs[i].files.length > 0) {
                return true;
            }
        }
        return false;
    }

    function queueAutosave() {
        dirty = true;
        setStatus('Modification detectee...', 'pending');
        if (timer) {
            clearTimeout(timer);
        }
        timer = setTimeout(function () {
            if (inFlight || hasSelectedFiles()) {
                return;
            }

            inFlight = true;
            dirty = false;
            setStatus('Sauvegarde en cours...', 'saving');
            var payload = new FormData(form);
            payload.set('autosave_mode', '1');

            fetch(form.getAttribute('data-autosave-endpoint') || window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: payload,
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json().then(function (json) {
                    return { ok: response.ok, json: json };
                });
            }).then(function (result) {
                inFlight = false;
                var saveAgain = dirty;
                if (!result.ok || !result.json || result.json.ok !== true) {
                    setStatus((result.json && result.json.message) ? result.json.message : 'Erreur de sauvegarde', 'error');
                    if (window.AppUI && typeof window.AppUI.toast === 'function') {
                        window.AppUI.toast('error', (result.json && result.json.message) ? result.json.message : 'Erreur de sauvegarde auto');
                    }
                    return;
                }
                if(window.updateContentCompletion)window.updateContentCompletion(result.json.requirements);
                if(saveAgain)queueAutosave();
                setStatus('Sauvegarde auto: ' + (result.json.at || ''), 'saved');
                if (window.AppUI && typeof window.AppUI.toast === 'function') {
                    window.AppUI.toast('success', 'Sauvegarde auto reussie');
                }
            }).catch(function () {
                inFlight = false;
                setStatus('Sauvegarde impossible (reseau)', 'error');
                if (window.AppUI && typeof window.AppUI.toast === 'function') {
                    window.AppUI.toast('error', 'Sauvegarde auto impossible (reseau)');
                }
            });
        }, 380);
    }

    form.querySelectorAll('textarea, select, input[type="text"], input[type="date"], input[type="number"], input[type="time"]').forEach(function (input) {
        input.addEventListener('blur', queueAutosave);
        input.addEventListener('change', queueAutosave);
    });
})();
</script>
<script>
(function(){
 var panel=document.querySelector('.tpack-composer-panel'); if(!panel)return;
 function value(name){var el=panel.querySelector('[name="'+name+'"]');return el?el.value.trim():'';}
 function generateTpackBrief(){var target=value('tpack_target'),objective=value('tpack_objective'),problem=value('tpack_problem'),product=value('tpack_product'),format=value('tpack_format'),cta=value('tpack_cta'),platform=value('tpack_platform'),hook=value('tpack_hook'); var brief=(hook?hook+'. ':'')+(target?'Pour '+target+' : ':'')+'montrer comment '+(product||'le produit')+' répond au problème « '+(problem||'besoin à préciser')+' » au format '+(format||'à définir')+', afin de renforcer '+(objective||'l objectif de communication')+'.'+(platform?' Plateforme : '+platform+'.':'')+(cta?' CTA : '+cta+'.':''); var out=panel.querySelector('[data-tpack-output]'); if(out)out.value=brief; return brief;}
 panel.querySelector('[data-tpack-generate]').addEventListener('click',generateTpackBrief);
 panel.querySelector('[data-tpack-apply]').addEventListener('click',function(){var brief=generateTpackBrief(),map={sujet:value('tpack_hook')||brief,message:brief,cible_libre:value('tpack_target'),reseau_cible:value('tpack_platform')}; Object.keys(map).forEach(function(name){var field=document.querySelector('[name="'+name+'"]');if(field){field.value=map[name];field.dispatchEvent(new Event('input',{bubbles:true}));}}); var objective=document.querySelector('[name="objectif_publication"]'); if(objective&&Array.from(objective.options).some(function(o){return o.value===value('tpack_objective');})){objective.value=value('tpack_objective');objective.dispatchEvent(new Event('change',{bubbles:true}));} });
})();
</script>
