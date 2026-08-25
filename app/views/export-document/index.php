<?php
$filters = $filters ?? [];
$clients = is_array($clients ?? null) ? $clients : [];
$plans = is_array($plans ?? null) ? $plans : [];
$availableFields = is_array($availableFields ?? null) ? $availableFields : [];
$defaultSelectedFields = is_array($defaultSelectedFields ?? null) ? $defaultSelectedFields : [];
$availableReportSections = is_array($availableReportSections ?? null) ? $availableReportSections : [];
$defaultReportSections = is_array($defaultReportSections ?? null) ? $defaultReportSections : [];
$selectedPeriod = (string) ($filters['period'] ?? 'current_month');
$fieldLabels = [
    'periode_mois' => 'Periode mois',
    'type_livrable' => 'Type livrable',
    'date_prevue' => 'Date prevue',
    'impact_global' => 'Impact global',
    'plan_script' => 'Plan script',
    'texte_script' => 'Texte script',
    'script_contenu' => 'Contenu script',
];
?>
<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Export documents</h2>
            <p class="panel-subtitle">Exporter calendriers editoriaux, scripts et rapports sur un ou plusieurs calendriers.</p>
        </div>
        <div class="toolbar-actions">
            <a class="button secondary" href="<?= htmlspecialchars(route_url('/calendrier')) ?>">Retour pilotage</a>
            <a class="button secondary" href="<?= htmlspecialchars(route_url('/settings')) ?>">Retour parametres</a>
        </div>
    </div>

    <form method="get" class="list-toolbar">
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
                <option value="current_month" <?= $selectedPeriod === 'current_month' ? 'selected' : '' ?>>Mois en cours</option>
                <option value="prev_month" <?= $selectedPeriod === 'prev_month' ? 'selected' : '' ?>>Mois precedent</option>
                <option value="last_3_months" <?= $selectedPeriod === 'last_3_months' ? 'selected' : '' ?>>3 derniers mois</option>
                <option value="next_month" <?= $selectedPeriod === 'next_month' ? 'selected' : '' ?>>Mois prochain</option>
                <option value="next_3_months" <?= $selectedPeriod === 'next_3_months' ? 'selected' : '' ?>>3 prochains mois</option>
                <option value="next_6_months" <?= $selectedPeriod === 'next_6_months' ? 'selected' : '' ?>>6 prochains mois</option>
            </select>
        </label>
        <label class="field toolbar-field"><span>Du</span><input type="date" name="from" value="<?= htmlspecialchars((string) ($filters['from'] ?? '')) ?>"></label>
        <label class="field toolbar-field"><span>Au</span><input type="date" name="to" value="<?= htmlspecialchars((string) ($filters['to'] ?? '')) ?>"></label>
        <div class="toolbar-actions"><button class="button" type="submit">Filtrer</button><a class="button secondary" href="<?= htmlspecialchars(route_url('/export-document')) ?>">Reinitialiser</a></div>
    </form>

    <form method="post" class="form-grid" data-download-form="true">
        <div class="field">
            <span>Calendriers a exporter</span>
            <div class="checkbox-grid">
                <?php foreach ($plans as $plan): ?>
                    <label class="checkbox-pill">
                        <input type="checkbox" name="plan_ids[]" value="<?= htmlspecialchars((string) ($plan['plan_id'] ?? 0)) ?>">
                        <span><?= htmlspecialchars((string) ($plan['client_nom'] ?? '')) ?> · <?= htmlspecialchars((string) ($plan['projet_nom'] ?? '')) ?> · <?= htmlspecialchars((string) ($plan['periode_mois'] ?? '')) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="field">
            <span>Champs a exporter</span>
            <div class="toolbar-actions" style="margin: 6px 0 10px;">
                <button type="button" class="button secondary" id="export-fields-all">Tout selectionner</button>
                <button type="button" class="button secondary" id="export-fields-none">Tout deselectionner</button>
            </div>
            <div class="checkbox-grid" id="export-fields-grid">
                <?php foreach ($availableFields as $field): ?>
                    <?php $fieldLabel = $fieldLabels[$field] ?? ucfirst(str_replace('_', ' ', $field)); ?>
                    <label class="checkbox-pill">
                        <input type="checkbox" name="selected_fields[]" value="<?= htmlspecialchars((string) $field) ?>" <?= in_array($field, $defaultSelectedFields, true) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars((string) $fieldLabel) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <label class="field">
            <span>Inclure scripts dans export calendrier</span>
            <label class="checkbox-pill"><input type="checkbox" name="include_scripts" value="1"> <span>Oui</span></label>
        </label>

        <div class="field">
            <span>Sections du rapport</span>
            <div class="checkbox-grid" id="report-sections-grid">
                <?php foreach ($availableReportSections as $section): ?>
                    <?php $label = ucfirst(str_replace('_', ' ', (string) $section)); ?>
                    <label class="checkbox-pill">
                        <input type="checkbox" name="report_sections[]" value="<?= htmlspecialchars((string) $section) ?>" <?= in_array($section, $defaultReportSections, true) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-actions">
            <button class="button" type="submit" name="action" value="export_calendar">Exporter calendrier editorial</button>
            <button class="button secondary" type="submit" name="action" value="export_calendar_pdf">PDF style calendrier</button>
            <button class="button secondary" type="submit" name="action" value="export_scripts">Exporter scripts</button>
            <button class="button secondary" type="submit" name="action" value="export_scripts_pdf">PDF scripts</button>
            <button class="button secondary" type="submit" name="action" value="export_reports">Exporter rapports</button>
            <button class="button secondary" type="submit" name="action" value="export_reports_pdf">PDF style rapports</button>
        </div>
    </form>
</section>

<script>
(function () {
    var allBtn = document.getElementById('export-fields-all');
    var noneBtn = document.getElementById('export-fields-none');
    var checkboxes = document.querySelectorAll('#export-fields-grid input[type="checkbox"]');

    if (allBtn) {
        allBtn.addEventListener('click', function () {
            checkboxes.forEach(function (checkbox) { checkbox.checked = true; });
        });
    }
    if (noneBtn) {
        noneBtn.addEventListener('click', function () {
            checkboxes.forEach(function (checkbox) { checkbox.checked = false; });
        });
    }
})();
</script>
