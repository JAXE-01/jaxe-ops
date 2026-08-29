<?php
$activeSection = $activeSection ?? '';
$settingsIncompleteSections = [];
$missingDefaults = 0;
foreach ((array) ($projectDefaults ?? []) as $defaultValue) { if (empty($defaultValue)) { $missingDefaults++; } }
if ($missingDefaults > 0) { $settingsIncompleteSections['defaults'] = $missingDefaults . ' manquant' . ($missingDefaults > 1 ? 's' : ''); }
if (empty($projectTypeOptions) || empty($subscriptionTypeOptions) || empty($contentObjectiveOptions)) { $settingsIncompleteSections['types'] = 'Incomplet'; }
if (trim((string) ($kpiNetworksConfigJson ?? '')) === '' || trim((string) ($kpiNetworksConfigJson ?? '')) === '[]') { $settingsIncompleteSections['kpi'] = 'Incomplet'; }
if (!empty($dolibarrConfig['enabled']) && (empty($dolibarrConfig['base_url']) || empty($dolibarrConfig['api_key']))) { $settingsIncompleteSections['integrations'] = 'Connexion incomplete'; }
$hasSocialCredential = false;
foreach (['facebook','linkedin','instagram','tiktok','youtube','whatsapp'] as $networkKey) {
    foreach ((array) ($apiIntegrationsConfig[$networkKey] ?? []) as $credentialKey => $credentialValue) {
        if ($credentialKey !== 'mode' && trim((string) $credentialValue) !== '') { $hasSocialCredential = true; break 2; }
    }
}
if (!$hasSocialCredential) { $settingsIncompleteSections['apis'] = 'A connecter'; }
$GLOBALS['settingsIncompleteSections'] = $settingsIncompleteSections;

function settings_section_url($section, array $extra = []) {
    $query = array_merge(['section' => $section], $extra);
    return route_url('/settings') . '?' . http_build_query($query);
}

function settings_section_card($section, $label, $hint, $isActive, array $extra = []) {
    $className = 'stat-card settings-section-card' . ($isActive ? ' active' : '');
    ?>
    <a class="<?= htmlspecialchars($className) ?>" data-settings-section="<?= htmlspecialchars($section) ?>" href="<?= htmlspecialchars(settings_section_url($section, $extra)) ?>">
        <span class="stat-label"><?= htmlspecialchars($label) ?></span>
        <span class="stat-link"><?= htmlspecialchars($hint) ?></span>
        <?php if (!empty($GLOBALS['settingsIncompleteSections'][$section])): ?><span class="settings-incomplete-badge"><?= htmlspecialchars($GLOBALS['settingsIncompleteSections'][$section]) ?></span><?php endif; ?>
    </a>
    <?php
}

function settings_wcag_hex_to_rgb($hex) {
    $hex = ltrim(trim((string) $hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6) {
        return [0, 0, 0];
    }
    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
}

function settings_wcag_relative_luminance($hex) {
    [$r, $g, $b] = settings_wcag_hex_to_rgb($hex);
    $channels = [$r / 255, $g / 255, $b / 255];
    $linear = array_map(static function ($channel) {
        return $channel <= 0.03928 ? ($channel / 12.92) : pow(($channel + 0.055) / 1.055, 2.4);
    }, $channels);
    return (0.2126 * $linear[0]) + (0.7152 * $linear[1]) + (0.0722 * $linear[2]);
}

function settings_wcag_contrast_ratio($foregroundHex, $backgroundHex) {
    $l1 = settings_wcag_relative_luminance($foregroundHex);
    $l2 = settings_wcag_relative_luminance($backgroundHex);
    $lighter = max($l1, $l2);
    $darker = min($l1, $l2);
    return ($lighter + 0.05) / ($darker + 0.05);
}

function settings_wcag_level($ratio) {
    $ratio = (float) $ratio;
    if ($ratio >= 7) {
        return 'AAA';
    }
    if ($ratio >= 4.5) {
        return 'AA';
    }
    if ($ratio >= 3) {
        return 'AA Large';
    }
    return 'Insuffisant';
}
?>
<div class="settings-workspace">
 <aside class="settings-secondary-sidebar" aria-label="Navigation des paramètres">
  <div class="settings-sidebar-head"><span class="settings-sidebar-eyebrow">Configuration</span><h2>Paramètres</h2><p>Sélectionnez une rubrique pour afficher ses options.</p><label class="settings-search"><span aria-hidden="true">⌕</span><input type="search" placeholder="Rechercher un paramètre" aria-label="Rechercher dans les paramètres" data-settings-search><kbd>Ctrl K</kbd></label><small class="settings-search-empty" data-settings-empty hidden>Aucune rubrique correspondante.</small></div>
  <details class="settings-sidebar-group" open><summary>Pilotage opérationnel</summary><nav>
   <?php settings_section_card('defaults', 'Rôles projet', 'Équipe et responsabilités par défaut', $activeSection === 'defaults'); ?>
   <?php settings_section_card('types', 'Types & workflow vidéo', 'Prestations et règles de production', $activeSection === 'types'); ?>
   <?php settings_section_card('kpi', 'Réseaux & KPI', 'Indicateurs et collecte', $activeSection === 'kpi'); ?>
   <?php if ($canManageSettings): ?><?php settings_section_card('permissions', 'Permissions par rôle', 'Matrice des accès', $activeSection === 'permissions'); ?><?php settings_section_card('overrides', 'Surcharges utilisateur', 'Exceptions individuelles', $activeSection === 'overrides', ['override_user_id' => $selectedOverrideUserId ?? 0]); ?><?php endif; ?>
  </nav></details>
  <details class="settings-sidebar-group" open><summary>Interface & qualité</summary><nav>
   <?php settings_section_card('appearance', 'Apparence', 'Couleurs et aperçu du calendrier', $activeSection === 'appearance'); ?>
   <?php settings_section_card('wcag', 'Contraste WCAG', 'Accessibilité de la palette', $activeSection === 'wcag'); ?>
  </nav></details>
  <?php if ($canViewIntegrations): ?><details class="settings-sidebar-group" open><summary>Connecteurs & API</summary><nav>
   <?php settings_section_card('integrations', 'Dolibarr', 'Connexion et mappings', $activeSection === 'integrations'); ?>
   <?php settings_section_card('apis', 'Réseaux sociaux', 'OAuth, webhooks et publication', $activeSection === 'apis'); ?>
  </nav></details><?php endif; ?>
  <?php if ($activeSection !== ''): ?><a class="settings-sidebar-overview" href="<?= htmlspecialchars(route_url('/settings')) ?>">← Vue d’ensemble</a><?php endif; ?>
 </aside>
 <main class="settings-main-pane">
<section class="panel settings-overview-panel <?= $activeSection !== '' ? 'has-active-section' : '' ?>">
    <div class="panel-head">
        <div>
            <h2>Parametres de pilotage</h2>
            <p class="panel-subtitle">Choisis une section pour travailler sans surcharge visuelle. Une section active masque les autres formulaires.</p>
        </div>
        <?php if ($activeSection !== ''): ?>
            <a class="button secondary" href="<?= htmlspecialchars(route_url('/settings')) ?>">Voir le tableau des sections</a>
        <?php endif; ?>
    </div>

    <div class="stats-grid settings-grid">
        <?php if ($this->can('subscriptions.view')): ?>
        <a class="stat-card" href="<?= htmlspecialchars(route_url('/abonnement')) ?>">
            <span class="stat-label">Abonnements</span>
            <span class="stat-value"><?= htmlspecialchars((string) $subscriptionCount) ?></span>
            <span class="stat-link">Configurer les packs reutilisables</span>
        </a>
        <?php endif; ?>
        <?php if ($this->can('users.view')): ?>
        <a class="stat-card" href="<?= htmlspecialchars(route_url('/user')) ?>">
            <span class="stat-label">Utilisateurs</span>
            <span class="stat-value"><?= htmlspecialchars((string) $userCount) ?></span>
            <span class="stat-link">Gerer les comptes et les roles</span>
        </a>
        <?php endif; ?>
        <?php if ($this->can('reporting.view')): ?>
        <a class="stat-card settings-card-muted" href="<?= htmlspecialchars(route_url('/reporting-metric')) ?>">
            <span class="stat-label">Metriques sociales</span>
            <span class="stat-value"><?= htmlspecialchars((string) $reportingMetricCount) ?></span>
            <span class="stat-link">Saisie manuelle, courbe KPI et export Excel</span>
        </a>
        <?php else: ?>
        <article class="stat-card settings-card-muted">
            <span class="stat-label">Metriques sociales</span>
            <span class="stat-value"><?= htmlspecialchars((string) $reportingMetricCount) ?></span>
            <span class="stat-link">Module technique conserve en back-office</span>
        </article>
        <?php endif; ?>
        <?php if ($this->can('settings.manage')): ?>
        <a class="stat-card" href="<?= htmlspecialchars(route_url('/trash')) ?>">
            <span class="stat-label">Corbeille uploads</span>
            <span class="stat-value">Ouvrir</span>
            <span class="stat-link">Suppression definitive depuis la corbeille</span>
        </a>
        <?php endif; ?>
        <article class="stat-card settings-card-summary">
            <span class="stat-label">Roles projet par defaut</span>
            <div class="settings-defaults-list">
                <span><strong>Communication:</strong> <?= htmlspecialchars($projectDefaultLabels['charge_compte_id'] ?? 'Non defini') ?></span>
                <span><strong>Clientele:</strong> <?= htmlspecialchars($projectDefaultLabels['charge_clientele_id'] ?? 'Non defini') ?></span>
                <span><strong>CM:</strong> <?= htmlspecialchars($projectDefaultLabels['cm_id'] ?? 'Non defini') ?></span>
                <span><strong>Createur:</strong> <?= htmlspecialchars($projectDefaultLabels['createur_id'] ?? 'Non defini') ?></span>
                <span><strong>Cadreur:</strong> <?= htmlspecialchars($projectDefaultLabels['cadreur_id'] ?? 'Non defini') ?></span>
                <span><strong>Videaste:</strong> <?= htmlspecialchars($projectDefaultLabels['videaste_id'] ?? 'Non defini') ?></span>
            </div>
            <span class="stat-link">Visible sans ouvrir le formulaire</span>
        </article>
    </div>

    <div class="settings-section-groups">
        <div class="settings-section-block">
            <h3>Pilotage operationnel</h3>
            <div class="settings-section-grid">
                <?php settings_section_card('defaults', 'Roles projet', 'Assignes par defaut et responsabilites client', $activeSection === 'defaults'); ?>
                <?php settings_section_card('types', 'Types & workflow video', 'Prestations, abonnements et regles de montage video', $activeSection === 'types'); ?>
                <?php settings_section_card('kpi', 'Reseaux & KPI', 'Ajouter/renommer reseaux et indicateurs de collecte', $activeSection === 'kpi'); ?>
                <?php if ($canManageSettings): ?>
                    <?php settings_section_card('permissions', 'Permissions par role', 'Matrice centrale des acces', $activeSection === 'permissions'); ?>
                    <?php settings_section_card('overrides', 'Surcharges utilisateur', 'Exceptions au-dessus des roles', $activeSection === 'overrides', ['override_user_id' => $selectedOverrideUserId ?? 0]); ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="settings-section-block">
            <h3>Interface et qualite visuelle</h3>
            <div class="settings-section-grid">
                <?php settings_section_card('appearance', 'Apercus couleurs', 'Palette du calendrier publication/global', $activeSection === 'appearance'); ?>
                <?php settings_section_card('wcag', 'Audit contraste WCAG', 'Controle des contrastes et priorites de correction', $activeSection === 'wcag'); ?>
            </div>
        </div>

        <?php if ($canViewIntegrations): ?>
            <div class="settings-section-block">
                <h3>Connecteurs et API</h3>
                <div class="settings-section-grid">
                    <?php settings_section_card('integrations', 'Dolibarr', 'Connexion, test API et mappings', $activeSection === 'integrations'); ?>
                    <?php settings_section_card('apis', 'APIs reseaux', 'Connexion reseaux sociaux et webhooks', $activeSection === 'apis'); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($activeSection === ''): ?>
        <div class="info-banner">Selectionne une section ci-dessus pour afficher son formulaire et masquer les autres zones de parametres.</div>
    <?php endif; ?>
</section>

<?php if ($activeSection === 'wcag'): ?>
<?php
$palette = [
    'bg' => '#e8f0f7',
    'bg-soft' => '#f4f8fc',
    'ink' => '#102a43',
    'ink-soft' => '#334e68',
    'muted' => '#5a6f84',
    'accent' => '#346d9b',
    'accent-strong' => '#23537a',
    'success' => '#3f6b5f',
    'warning' => '#8a5a1f',
    'danger' => '#8f3d45',
];

$contrastChecks = [
    ['label' => 'Texte principal sur fond global', 'fg' => 'ink', 'bg' => 'bg'],
    ['label' => 'Texte secondaire sur fond global', 'fg' => 'ink-soft', 'bg' => 'bg'],
    ['label' => 'Texte discret sur fond global', 'fg' => 'muted', 'bg' => 'bg'],
    ['label' => 'Texte principal sur fond doux', 'fg' => 'ink', 'bg' => 'bg-soft'],
    ['label' => 'Texte secondaire sur fond doux', 'fg' => 'ink-soft', 'bg' => 'bg-soft'],
    ['label' => 'Accent sur fond global', 'fg' => 'accent', 'bg' => 'bg'],
    ['label' => 'Accent fort sur fond global', 'fg' => 'accent-strong', 'bg' => 'bg'],
    ['label' => 'Succes sur fond global', 'fg' => 'success', 'bg' => 'bg'],
    ['label' => 'Alerte sur fond global', 'fg' => 'warning', 'bg' => 'bg'],
    ['label' => 'Erreur sur fond global', 'fg' => 'danger', 'bg' => 'bg'],
];

$stateChecks = [
    ['label' => 'Bouton primaire (normal)', 'fg_hex' => '#f8fbff', 'bg_hex' => '#23537a', 'scope' => 'state'],
    ['label' => 'Bouton secondaire (normal)', 'fg' => 'ink-soft', 'bg_hex' => '#eaf3fa', 'scope' => 'state'],
    ['label' => 'Bouton secondaire (hover)', 'fg' => 'ink-soft', 'bg' => 'bg-soft', 'scope' => 'state'],
    ['label' => 'Navigation (hover)', 'fg' => 'ink-soft', 'bg' => 'bg-soft', 'scope' => 'state'],
    ['label' => 'Bouton desactive', 'fg' => 'ink-soft', 'bg_hex' => '#e9eff5', 'scope' => 'state'],
    ['label' => 'Focus ring vs fond global', 'fg_hex' => '#0b5f8f', 'bg' => 'bg', 'scope' => 'state'],
    ['label' => 'Badge statut Annulee', 'fg' => 'danger', 'bg_hex' => '#f3e6e8', 'scope' => 'state'],
    ['label' => 'Badge statut En cours', 'fg_hex' => '#1c5d8f', 'bg_hex' => '#cce2f4', 'scope' => 'state'],
    ['label' => 'Badge statut Brouillon', 'fg' => 'ink-soft', 'bg_hex' => '#e9eff5', 'scope' => 'state'],
];

$allChecks = [];
foreach ($contrastChecks as $check) {
    $check['scope'] = 'palette';
    $allChecks[] = $check;
}
foreach ($stateChecks as $check) {
    $allChecks[] = $check;
}

$auditRows = [];
$priorityRows = [];
foreach ($allChecks as $check) {
    $fgKey = (string) ($check['fg'] ?? '');
    $bgKey = (string) ($check['bg'] ?? '');
    $fgHex = (string) ($check['fg_hex'] ?? ($palette[$fgKey] ?? '#000000'));
    $bgHex = (string) ($check['bg_hex'] ?? ($palette[$bgKey] ?? '#ffffff'));
    $ratio = settings_wcag_contrast_ratio($fgHex, $bgHex);
    $level = settings_wcag_level($ratio);
    $badgeClass = $level === 'AAA' ? 'status-terminee' : ($level === 'AA' ? 'status-en-cours' : ($level === 'AA Large' ? 'status-a-faire' : 'status-annulee'));

    $auditRows[] = [
        'scope' => (string) ($check['scope'] ?? 'palette'),
        'label' => (string) ($check['label'] ?? ''),
        'fg_key' => $fgKey,
        'fg_hex' => $fgHex,
        'bg_key' => $bgKey,
        'bg_hex' => $bgHex,
        'ratio' => $ratio,
        'level' => $level,
        'badge_class' => $badgeClass,
    ];

    if ($level === 'Insuffisant' || $level === 'AA Large') {
        $recommendation = 'Assombrir la couleur texte ou eclaircir le fond pour atteindre au moins AA.';
        if ($fgKey === 'muted') {
            $recommendation = 'Remplacer muted par ink-soft sur cet usage ou augmenter le poids/contraste de texte.';
        } elseif ($fgKey === 'warning') {
            $recommendation = 'Utiliser un warning plus sombre pour le texte (ex: #8a5a1f) sur fond clair.';
        } elseif ($fgKey === 'danger') {
            $recommendation = 'Utiliser un danger plus sombre pour le texte (ex: #8f3d45) sur fond clair.';
        }

        $priorityRows[] = [
            'scope' => (string) ($check['scope'] ?? 'palette'),
            'label' => (string) ($check['label'] ?? ''),
            'ratio' => $ratio,
            'level' => $level,
            'recommendation' => $recommendation,
        ];
    }
}

usort($priorityRows, static function ($left, $right) {
    return ($left['ratio'] <=> $right['ratio']);
});
?>
<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Audit contraste WCAG (palette)</h2>
            <p class="panel-subtitle">Controle automatique des contrastes de la palette UI avec seuils WCAG AA/AAA.</p>
        </div>
        <button class="button secondary" type="button" id="settings-contrast-export">Exporter CSV</button>
    </div>
    <div class="table-wrap compact-table settings-contrast-audit">
        <table id="settings-contrast-table">
            <thead>
                <tr>
                    <th>Scope</th>
                    <th>Verification</th>
                    <th>Texte</th>
                    <th>Fond</th>
                    <th>Ratio</th>
                    <th>Niveau</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($auditRows as $row): ?>
                    <tr>
                        <td><span class="chip"><?= htmlspecialchars($row['scope'] === 'state' ? 'Etat' : 'Palette') ?></span></td>
                        <td><?= htmlspecialchars((string) ($row['label'] ?? '')) ?></td>
                        <td><span class="chip"><?= htmlspecialchars((string) ($row['fg_key'] !== '' ? $row['fg_key'] : 'hex')) ?> · <?= htmlspecialchars((string) ($row['fg_hex'] ?? '')) ?></span></td>
                        <td><span class="chip"><?= htmlspecialchars((string) ($row['bg_key'] !== '' ? $row['bg_key'] : 'hex')) ?> · <?= htmlspecialchars((string) ($row['bg_hex'] ?? '')) ?></span></td>
                        <td><?= htmlspecialchars(number_format((float) ($row['ratio'] ?? 0), 2)) ?>:1</td>
                        <td><span class="status-badge <?= htmlspecialchars((string) ($row['badge_class'] ?? 'status-a-faire')) ?>"><?= htmlspecialchars((string) ($row['level'] ?? '')) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="panel inset-panel" style="margin-top:14px;">
        <div class="panel-head">
            <div>
                <h3>Corrections palette priorisees</h3>
                <p class="panel-subtitle">Ordre prioritaire par ratio le plus faible vers le plus proche du seuil AA.</p>
            </div>
        </div>
        <?php if (!empty($priorityRows)): ?>
            <ol class="requirement-list">
                <?php foreach ($priorityRows as $row): ?>
                    <li class="requirement-item is-missing">
                        <span class="requirement-state"><?= htmlspecialchars((string) ($row['level'] ?? '')) ?></span>
                        <span>
                            <?= htmlspecialchars((string) ($row['label'] ?? '')) ?> ·
                            Ratio <?= htmlspecialchars(number_format((float) ($row['ratio'] ?? 0), 2)) ?>:1 ·
                            <?= htmlspecialchars((string) ($row['recommendation'] ?? '')) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <p class="empty-state">Aucune correction critique detectee: toutes les paires passent au moins AA Large.</p>
        <?php endif; ?>
    </div>

    <script>
    (function () {
        var table = document.getElementById('settings-contrast-table');
        var button = document.getElementById('settings-contrast-export');
        if (!table || !button) {
            return;
        }

        button.addEventListener('click', function () {
            var rows = Array.prototype.slice.call(table.querySelectorAll('tr'));
            var lines = rows.map(function (row) {
                var cells = Array.prototype.slice.call(row.querySelectorAll('th, td'));
                return cells.map(function (cell) {
                    var text = (cell.textContent || '').replace(/\s+/g, ' ').trim();
                    return '"' + text.replace(/"/g, '""') + '"';
                }).join(',');
            });

            var csv = "\uFEFF" + lines.join('\n');
            var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            var url = URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = url;
            link.download = 'audit-contraste-wcag-' + new Date().toISOString().slice(0, 10) + '.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        });
    })();
    </script>
</section>
<?php endif; ?>

<?php if ($activeSection === 'defaults'): ?>
<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Roles par defaut des projets</h2>
            <p class="panel-subtitle">Le createur gere briefs et scripts, le cadreur gere les tournages, le videaste gere les montages.</p>
        </div>
    </div>

    <div class="panel inset-panel" style="margin-bottom:14px;">
        <div class="panel-head">
            <div>
                <h3>Identite de navigation</h3>
                <p class="panel-subtitle">Nom, logo et sous-titre affiches dans la barre de navigation unique.</p>
            </div>
        </div>
        <?php if (!$canManageSettings): ?>
            <div class="settings-defaults-list">
                <span><strong>Nom:</strong> <?= htmlspecialchars((string) ($brandingConfig['app_name'] ?? 'Strax')) ?></span>
                <span><strong>Sous-titre:</strong> <?= htmlspecialchars((string) ($brandingConfig['brand_caption'] ?? '')) ?></span>
                <span><strong>Logo:</strong> <?= htmlspecialchars((string) ($brandingConfig['logo_url'] ?? 'Par defaut')) ?></span>
            </div>
        <?php else: ?>
            <form method="post" class="form-grid" style="margin-top:8px;">
                <input type="hidden" name="section" value="defaults">
                <input type="hidden" name="settings_action" value="save_branding">
                <label class="field">
                    <span>Nom de l application</span>
                    <input type="text" name="app_name" value="<?= htmlspecialchars((string) ($brandingConfig['app_name'] ?? 'Strax')) ?>" maxlength="80" placeholder="Strax">
                </label>
                <label class="field">
                    <span>URL du logo</span>
                    <input type="text" name="logo_url" value="<?= htmlspecialchars((string) ($brandingConfig['logo_url'] ?? '')) ?>" maxlength="255" placeholder="/public/assets/logo.svg ou https://...">
                </label>
                <label class="field">
                    <span>Sous-titre</span>
                    <input type="text" name="brand_caption" value="<?= htmlspecialchars((string) ($brandingConfig['brand_caption'] ?? 'Operations editoriales et pilotage client')) ?>" maxlength="160" placeholder="Operations editoriales et pilotage client">
                </label>
                <div class="form-actions">
                    <button class="button" type="submit">Enregistrer nom et logo</button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <?php if (!$canManageSettings): ?>
        <div class="info-banner">Lecture seule: seule une personne autorisee en administration peut modifier ces reglages.</div>
    <?php else: ?>
        <form method="post" class="form-grid settings-form-grid">
            <input type="hidden" name="section" value="defaults">
            <input type="hidden" name="settings_action" value="save_defaults">
            <label class="field">
                <span>Charge de communication</span>
                <select name="charge_compte_id">
                    <option value="">Selectionner</option>
                    <?php foreach (($defaultRoleOptions['charge_compte_id'] ?? []) as $optionValue => $label): ?>
                        <option value="<?= htmlspecialchars((string) $optionValue) ?>" <?= (string) ($projectDefaults['charge_compte_id'] ?? '') === (string) $optionValue ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">
                <span>Charge de clientele</span>
                <select name="charge_clientele_id">
                    <option value="">Selectionner</option>
                    <?php foreach (($defaultRoleOptions['charge_clientele_id'] ?? []) as $optionValue => $label): ?>
                        <option value="<?= htmlspecialchars((string) $optionValue) ?>" <?= (string) ($projectDefaults['charge_clientele_id'] ?? '') === (string) $optionValue ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">
                <span>Community manager</span>
                <select name="cm_id">
                    <option value="">Selectionner</option>
                    <?php foreach (($defaultRoleOptions['cm_id'] ?? []) as $optionValue => $label): ?>
                        <option value="<?= htmlspecialchars((string) $optionValue) ?>" <?= (string) ($projectDefaults['cm_id'] ?? '') === (string) $optionValue ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">
                <span>Createur contenu</span>
                <select name="createur_id">
                    <option value="">Selectionner</option>
                    <?php foreach (($defaultRoleOptions['createur_id'] ?? []) as $optionValue => $label): ?>
                        <option value="<?= htmlspecialchars((string) $optionValue) ?>" <?= (string) ($projectDefaults['createur_id'] ?? '') === (string) $optionValue ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">
                <span>Cadreur</span>
                <select name="cadreur_id">
                    <option value="">Selectionner</option>
                    <?php foreach (($defaultRoleOptions['cadreur_id'] ?? []) as $optionValue => $label): ?>
                        <option value="<?= htmlspecialchars((string) $optionValue) ?>" <?= (string) ($projectDefaults['cadreur_id'] ?? '') === (string) $optionValue ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">
                <span>Videaste montage</span>
                <select name="videaste_id">
                    <option value="">Selectionner</option>
                    <?php foreach (($defaultRoleOptions['videaste_id'] ?? []) as $optionValue => $label): ?>
                        <option value="<?= htmlspecialchars((string) $optionValue) ?>" <?= (string) ($projectDefaults['videaste_id'] ?? '') === (string) $optionValue ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="form-actions">
                <button class="button" type="submit">Enregistrer les valeurs par defaut</button>
            </div>
        </form>

        <form method="post" class="settings-reset-form">
            <input type="hidden" name="section" value="defaults">
            <input type="hidden" name="settings_action" value="reset_defaults">
            <button class="button secondary" type="submit" onclick="return confirm('Reinitialiser les roles par defaut des projets ?');">Reinitialiser les valeurs par defaut</button>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($activeSection === 'kpi'): ?>
<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Configuration reseaux et indicateurs KPI</h2>
            <p class="panel-subtitle">Cette configuration alimente la Collecte KPI des taches pipeline et les ecrans de reporting. LinkedIn peut etre ajoute ici ou renomme librement.</p>
        </div>
    </div>

    <?php if ($canManageSettings): ?>
        <form method="post" class="form-grid">
            <input type="hidden" name="section" value="kpi">
            <input type="hidden" name="settings_action" value="save_kpi_networks_config">

            <label class="field">
                <span>JSON reseaux KPI</span>
                <textarea name="kpi_networks_config_json" rows="18" spellcheck="false"><?= htmlspecialchars((string) ($kpiNetworksConfigJson ?? '{}')) ?></textarea>
            </label>
            <p class="mini-text">Format attendu: un objet par reseau (ex: linkedin) avec <strong>label</strong>, tableau <strong>kpis</strong> et optionnellement <strong>weights</strong> (poids de scoring par KPI). Chaque KPI contient au minimum <strong>name</strong>, <strong>label</strong> et <strong>type</strong> (integer ou float).</p>

            <div class="form-actions">
                <button class="button" type="submit">Enregistrer la configuration KPI</button>
            </div>
        </form>
    <?php else: ?>
        <article class="stat-card settings-card-summary">
            <span class="stat-label">Configuration active</span>
            <pre style="white-space: pre-wrap; margin: 0;"><?= htmlspecialchars((string) ($kpiNetworksConfigJson ?? '{}')) ?></pre>
        </article>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($activeSection === 'types'): ?>
<?php $workflowRulesConfig = is_array($workflowRulesConfig ?? null) ? $workflowRulesConfig : ['require_second_montage_video' => false]; ?>
<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Types configurables et workflow video</h2>
            <p class="panel-subtitle">Une ligne = une valeur. Cette section contient aussi la regle "2e export en fin de montage".</p>
        </div>
    </div>

    <?php if ($canManageSettings): ?>
        <div class="settings-type-grid">
            <form method="post" class="form-grid">
                <input type="hidden" name="section" value="types">
                <input type="hidden" name="settings_action" value="save_project_types">
                <label class="field">
                    <span>Types de projet</span>
                    <textarea name="project_type_options"><?= htmlspecialchars(implode(PHP_EOL, (array) ($projectTypeOptions ?? []))) ?></textarea>
                </label>
                <div class="form-actions">
                    <button class="button" type="submit">Enregistrer les types de projet</button>
                </div>
            </form>

            <form method="post" class="form-grid">
                <input type="hidden" name="section" value="types">
                <input type="hidden" name="settings_action" value="save_subscription_types">
                <label class="field">
                    <span>Types d abonnement</span>
                    <textarea name="subscription_type_options"><?= htmlspecialchars(implode(PHP_EOL, (array) ($subscriptionTypeOptions ?? []))) ?></textarea>
                </label>
                <div class="form-actions">
                    <button class="button" type="submit">Enregistrer les types d abonnement</button>
                </div>
            </form>

            <form method="post" class="form-grid">
                <input type="hidden" name="section" value="types">
                <input type="hidden" name="settings_action" value="save_content_objectives">
                <label class="field">
                    <span>Objectifs de contenu</span>
                    <textarea name="content_objective_options"><?= htmlspecialchars(implode(PHP_EOL, (array) ($contentObjectiveOptions ?? []))) ?></textarea>
                </label>
                <div class="form-actions">
                    <button class="button" type="submit">Enregistrer les objectifs de contenu</button>
                </div>
            </form>

            <form method="post" class="form-grid"><input type="hidden" name="section" value="types"><input type="hidden" name="settings_action" value="save_validation_policy"><?php require __DIR__ . '/validation-policy.php'; ?><button class="button" type="submit">Enregistrer les validations</button></form><form method="post" class="form-grid" id="workflow-video-rule">
                <input type="hidden" name="section" value="types">
                <input type="hidden" name="settings_action" value="save_workflow_rules">
                <label class="settings-checkbox">
                    <input type="checkbox" name="require_second_montage_video" value="1" <?= !empty($workflowRulesConfig['require_second_montage_video']) ? 'checked' : '' ?>>
                    Exiger 2 exports video en fin de tache Montage (avec musique + sans musique)
                </label>
                <div class="mini-text">Par defaut, cette option est desactivee: une seule video finale suffit pour valider la tache Montage.</div>
                <div class="form-actions">
                    <button class="button" type="submit">Enregistrer la regle de workflow video</button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="settings-type-grid">
            <article class="stat-card settings-card-summary">
                <span class="stat-label">Types de projet</span>
                <div class="settings-defaults-list">
                    <?php foreach (($projectTypeOptions ?? []) as $type): ?>
                        <span><?= htmlspecialchars($type) ?></span>
                    <?php endforeach; ?>
                </div>
            </article>
            <article class="stat-card settings-card-summary">
                <span class="stat-label">Types d abonnement</span>
                <div class="settings-defaults-list">
                    <?php foreach (($subscriptionTypeOptions ?? []) as $type): ?>
                        <span><?= htmlspecialchars($type) ?></span>
                    <?php endforeach; ?>
                </div>
            </article>
            <article class="stat-card settings-card-summary">
                <span class="stat-label">Objectifs de contenu</span>
                <div class="settings-defaults-list">
                    <?php foreach (($contentObjectiveOptions ?? []) as $type): ?>
                        <span><?= htmlspecialchars($type) ?></span>
                    <?php endforeach; ?>
                </div>
            </article>
            <article class="stat-card settings-card-summary">
                <span class="stat-label">Workflow video</span>
                <div class="settings-defaults-list">
                    <span>2e export montage requis: <?= !empty($workflowRulesConfig['require_second_montage_video']) ? 'Oui' : 'Non (optionnel)' ?></span>
                </div>
            </article>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($activeSection === 'appearance'): ?>
<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Apercus et options de modification des couleurs</h2>
            <p class="panel-subtitle">Personnalise chaque etat du calendrier publication/global avec apercu immediat.</p>
        </div>
    </div>

    <?php
    $calendarColorScheme = is_array($calendarColorScheme ?? null) ? $calendarColorScheme : [];
    $calendarColorDefaults = is_array($calendarColorDefaults ?? null) ? $calendarColorDefaults : [];
    ?>

    <?php if ($canManageSettings): ?>
        <form method="post" class="form-grid">
            <input type="hidden" name="section" value="appearance">
            <input type="hidden" name="settings_action" value="save_calendar_colors">

            <div class="settings-type-grid settings-color-grid">
                <?php foreach ($calendarColorScheme as $stateKey => $palette): ?>
                    <?php
                    $label = (string) ($palette['label'] ?? ($calendarColorDefaults[$stateKey]['label'] ?? $stateKey));
                    $bg = (string) ($palette['bg'] ?? '#FFFFFF');
                    $border = (string) ($palette['border'] ?? '#DDDDDD');
                    $text = (string) ($palette['text'] ?? '#111111');
                    ?>
                    <article class="stat-card settings-card-summary settings-color-card" data-calendar-color-card>
                        <span class="stat-label"><?= htmlspecialchars($label) ?></span>
                        <div class="chip settings-color-preview" data-calendar-color-preview style="background: <?= htmlspecialchars($bg) ?>; border-color: <?= htmlspecialchars($border) ?>; color: <?= htmlspecialchars($text) ?>;">
                            <span class="settings-color-dot" data-calendar-color-dot style="background: <?= htmlspecialchars($text) ?>;"></span>
                            Apercu calendrier
                        </div>
                        <div class="form-grid settings-color-fields">
                            <label class="field settings-color-field">
                                <span>Fond</span>
                                <input class="settings-color-input" type="color" data-color-role="bg" name="calendar_colors[<?= htmlspecialchars($stateKey) ?>][bg]" value="<?= htmlspecialchars($bg) ?>">
                                <small class="settings-color-code" data-color-code="bg"><?= htmlspecialchars(strtoupper($bg)) ?></small>
                            </label>
                            <label class="field settings-color-field">
                                <span>Bordure</span>
                                <input class="settings-color-input" type="color" data-color-role="border" name="calendar_colors[<?= htmlspecialchars($stateKey) ?>][border]" value="<?= htmlspecialchars($border) ?>">
                                <small class="settings-color-code" data-color-code="border"><?= htmlspecialchars(strtoupper($border)) ?></small>
                            </label>
                            <label class="field settings-color-field">
                                <span>Texte</span>
                                <input class="settings-color-input" type="color" data-color-role="text" name="calendar_colors[<?= htmlspecialchars($stateKey) ?>][text]" value="<?= htmlspecialchars($text) ?>">
                                <small class="settings-color-code" data-color-code="text"><?= htmlspecialchars(strtoupper($text)) ?></small>
                            </label>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="form-actions settings-action-row">
                <button class="button" type="submit">Enregistrer la palette calendrier</button>
            </div>
        </form>

        <form method="post" class="settings-reset-form">
            <input type="hidden" name="section" value="appearance">
            <input type="hidden" name="settings_action" value="reset_calendar_colors">
            <button class="button secondary" type="submit" onclick="return confirm('Reinitialiser la palette du calendrier ?');">Reinitialiser la palette</button>
        </form>

        <script>
        (function () {
            var cards = document.querySelectorAll('[data-calendar-color-card]');
            cards.forEach(function (card) {
                var preview = card.querySelector('[data-calendar-color-preview]');
                var dot = card.querySelector('[data-calendar-color-dot]');
                if (!preview || !dot) {
                    return;
                }

                var bgInput = card.querySelector('input[data-color-role="bg"]');
                var borderInput = card.querySelector('input[data-color-role="border"]');
                var textInput = card.querySelector('input[data-color-role="text"]');
                var bgCode = card.querySelector('[data-color-code="bg"]');
                var borderCode = card.querySelector('[data-color-code="border"]');
                var textCode = card.querySelector('[data-color-code="text"]');
                var syncPreview = function () {
                    if (bgInput) {
                        preview.style.background = bgInput.value;
                        if (bgCode) {
                            bgCode.textContent = String(bgInput.value || '').toUpperCase();
                        }
                    }
                    if (borderInput) {
                        preview.style.borderColor = borderInput.value;
                        if (borderCode) {
                            borderCode.textContent = String(borderInput.value || '').toUpperCase();
                        }
                    }
                    if (textInput) {
                        preview.style.color = textInput.value;
                        dot.style.background = textInput.value;
                        if (textCode) {
                            textCode.textContent = String(textInput.value || '').toUpperCase();
                        }
                    }
                };

                [bgInput, borderInput, textInput].forEach(function (input) {
                    if (!input) {
                        return;
                    }
                    input.addEventListener('input', syncPreview);
                    input.addEventListener('change', syncPreview);
                });
            });
        })();
        </script>
    <?php else: ?>
        <div class="settings-type-grid settings-color-grid">
            <?php foreach ($calendarColorScheme as $stateKey => $palette): ?>
                <?php
                $label = (string) ($palette['label'] ?? ($calendarColorDefaults[$stateKey]['label'] ?? $stateKey));
                $bg = (string) ($palette['bg'] ?? '#FFFFFF');
                $border = (string) ($palette['border'] ?? '#DDDDDD');
                $text = (string) ($palette['text'] ?? '#111111');
                ?>
                <article class="stat-card settings-card-summary settings-color-card">
                    <span class="stat-label"><?= htmlspecialchars($label) ?></span>
                    <div class="chip settings-color-preview" style="background: <?= htmlspecialchars($bg) ?>; border-color: <?= htmlspecialchars($border) ?>; color: <?= htmlspecialchars($text) ?>;">
                        <span class="settings-color-dot" style="background: <?= htmlspecialchars($text) ?>;"></span>
                        Apercu calendrier
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($activeSection === 'permissions' && $canManageSettings): ?>
<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Permissions par role</h2>
            <p class="panel-subtitle">La matrice ci-dessous definit la base par role. Les surcharges utilisateur passent au-dessus.</p>
        </div>
    </div>

    <form method="post" class="form-grid">
        <input type="hidden" name="section" value="permissions">
        <input type="hidden" name="settings_action" value="save_role_permissions">
        <div class="table-wrap">
            <table class="data-table settings-permission-table">
                <thead>
                <tr>
                    <th>Permission</th>
                    <?php foreach (array_keys($rolePermissionMatrix ?? []) as $role): ?>
                        <th><?= htmlspecialchars(ModuleRegistry::roleOptions()[$role] ?? $role) ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach (($permissionGroups ?? []) as $groupLabel => $permissions): ?>
                    <tr>
                        <th colspan="<?= htmlspecialchars((string) (count($rolePermissionMatrix ?? []) + 1)) ?>" class="settings-permission-group"><?= htmlspecialchars($groupLabel) ?></th>
                    </tr>
                    <?php foreach ($permissions as $permissionKey => $meta): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($meta['label']) ?></strong>
                                <div class="table-cell-hint"><?= htmlspecialchars($permissionKey) ?></div>
                            </td>
                            <?php foreach (($rolePermissionMatrix ?? []) as $role => $permissionMap): ?>
                                <td>
                                    <label class="settings-checkbox">
                                        <input type="checkbox" name="role_permissions[<?= htmlspecialchars($role) ?>][<?= htmlspecialchars($permissionKey) ?>]" value="1" <?= !empty($permissionMap[$permissionKey]) ? 'checked' : '' ?>>
                                        <span>Autoriser</span>
                                    </label>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="form-actions">
            <button class="button" type="submit">Enregistrer la matrice par role</button>
        </div>
    </form>
</section>
<?php endif; ?>

<?php if ($activeSection === 'overrides' && $canManageSettings): ?>
<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Surcharges par utilisateur</h2>
            <p class="panel-subtitle">Utilise Inherit pour conserver la matrice du role, Allow pour ouvrir un acces, Deny pour le bloquer.</p>
        </div>
    </div>

    <form method="get" class="settings-inline-form">
        <input type="hidden" name="section" value="overrides">
        <label class="field settings-inline-field">
            <span>Utilisateur cible</span>
            <select name="override_user_id" onchange="this.form.submit()">
                <?php foreach (($userOptions ?? []) as $optionValue => $label): ?>
                    <option value="<?= htmlspecialchars((string) $optionValue) ?>" <?= (string) ($selectedOverrideUserId ?? '') === (string) $optionValue ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <form method="post" class="form-grid">
        <input type="hidden" name="section" value="overrides">
        <input type="hidden" name="settings_action" value="save_user_permissions">
        <input type="hidden" name="override_user_id" value="<?= htmlspecialchars((string) ($selectedOverrideUserId ?? 0)) ?>">

        <div class="settings-override-grid">
            <?php foreach (($permissionGroups ?? []) as $groupLabel => $permissions): ?>
                <article class="stat-card settings-override-card">
                    <span class="stat-label"><?= htmlspecialchars($groupLabel) ?></span>
                    <div class="settings-override-list">
                        <?php foreach ($permissions as $permissionKey => $meta): ?>
                            <label class="field settings-override-field">
                                <span><?= htmlspecialchars($meta['label']) ?></span>
                                <select name="user_permissions[<?= htmlspecialchars($permissionKey) ?>]">
                                    <?php foreach (['inherit' => 'Inherit', 'allow' => 'Allow', 'deny' => 'Deny'] as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value) ?>" <?= ($selectedUserOverrides[$permissionKey] ?? 'inherit') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="form-actions">
            <button class="button" type="submit">Enregistrer les surcharges utilisateur</button>
        </div>
    </form>
</section>
<?php endif; ?>

<?php if ($activeSection === 'integrations' && $canViewIntegrations): ?>
<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Integration Dolibarr</h2>
            <p class="panel-subtitle">Connexion REST, test API et tables de mapping locales pour preparer les futures synchronisations metier.</p>
        </div>
    </div>

    <div class="settings-mini-grid">
        <article class="stat-card settings-card-summary">
            <span class="stat-label">Etat</span>
            <div class="settings-defaults-list">
                <span><?= !empty($dolibarrConfig['enabled']) ? 'Active' : 'Inactive' ?></span>
                <span><?= !empty($dolibarrConfig['base_url']) ? htmlspecialchars($dolibarrConfig['base_url']) : 'URL non renseignee' ?></span>
            </div>
        </article>
        <article class="stat-card settings-card-summary">
            <span class="stat-label">Mappings utilisateurs</span>
            <div class="settings-defaults-list">
                <span>Total: <?= htmlspecialchars((string) ($dolibarrUserStats['total'] ?? 0)) ?></span>
                <span>Mappes: <?= htmlspecialchars((string) ($dolibarrUserStats['mapped'] ?? 0)) ?></span>
                <span>Non mappes: <?= htmlspecialchars((string) ($dolibarrUserStats['unmapped'] ?? 0)) ?></span>
            </div>
        </article>
        <article class="stat-card settings-card-summary">
            <span class="stat-label">Mappings projets</span>
            <div class="settings-defaults-list">
                <span>Total: <?= htmlspecialchars((string) ($dolibarrProjectStats['total'] ?? 0)) ?></span>
                <span>Mappes: <?= htmlspecialchars((string) ($dolibarrProjectStats['mapped'] ?? 0)) ?></span>
                <span>Non mappes: <?= htmlspecialchars((string) ($dolibarrProjectStats['unmapped'] ?? 0)) ?></span>
            </div>
        </article>
    </div>

    <?php if ($canManageIntegrations): ?>
        <form method="post" class="form-grid settings-dolibarr-form">
            <input type="hidden" name="section" value="integrations">
            <div class="field field-checkbox-inline">
                <span>Activation</span>
                <label class="settings-checkbox">
                    <input type="checkbox" name="dolibarr_enabled" value="1" <?= !empty($dolibarrConfig['enabled']) ? 'checked' : '' ?>>
                    <span>Activer la connexion Dolibarr</span>
                </label>
            </div>
            <label class="field">
                <span>URL Dolibarr</span>
                <input type="text" name="dolibarr_base_url" value="<?= htmlspecialchars((string) ($dolibarrConfig['base_url'] ?? '')) ?>" placeholder="https://erp.example.com/dolibarr/htdocs">
            </label>
            <label class="field">
                <span>Cle API</span>
                <input type="text" name="dolibarr_api_key" value="<?= htmlspecialchars((string) ($dolibarrConfig['api_key'] ?? '')) ?>" placeholder="DOLAPIKEY utilisateur">
            </label>
            <label class="field">
                <span>Entite Dolibarr</span>
                <input type="text" name="dolibarr_entity" value="<?= htmlspecialchars((string) ($dolibarrConfig['entity'] ?? '')) ?>" placeholder="Optionnel si multicompany">
            </label>
            <div class="form-actions settings-action-row">
                <button class="button" type="submit" name="settings_action" value="save_dolibarr">Enregistrer la connexion</button>
                <button class="button secondary" type="submit" name="settings_action" value="test_dolibarr">Tester l API</button>
                <button class="button secondary" type="submit" name="settings_action" value="sync_dolibarr_users">Synchroniser les utilisateurs</button>
                <button class="button secondary" type="submit" name="settings_action" value="sync_dolibarr_projects">Synchroniser les projets</button>
            </div>
        </form>
    <?php else: ?>
        <div class="info-banner">Lecture seule: la configuration de connexion et les synchronisations Dolibarr sont reservees aux profils autorises.</div>
    <?php endif; ?>

    <div class="settings-type-grid">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Utilisateur Dolibarr</th>
                    <th>Email</th>
                    <th>Compte local detecte</th>
                    <th>Derniere synchro</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($dolibarrUserMappings)): ?>
                    <tr><td colspan="4">Aucun utilisateur Dolibarr synchronise pour le moment.</td></tr>
                <?php endif; ?>
                <?php foreach (($dolibarrUserMappings ?? []) as $mapping): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($mapping['remote_name']) ?></strong>
                            <div class="table-cell-hint">#<?= htmlspecialchars((string) $mapping['dolibarr_user_id']) ?><?= !empty($mapping['remote_login']) ? ' · ' . htmlspecialchars($mapping['remote_login']) : '' ?></div>
                        </td>
                        <td><?= htmlspecialchars((string) ($mapping['remote_email'] ?? '')) ?: '-' ?></td>
                        <td><?= htmlspecialchars((string) ($mapping['local_user_name'] ?? 'Non mappe')) ?></td>
                        <td><?= htmlspecialchars((string) ($mapping['last_synced_at'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Projet Dolibarr</th>
                    <th>Tiers</th>
                    <th>Projet local detecte</th>
                    <th>Derniere synchro</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($dolibarrProjectMappings)): ?>
                    <tr><td colspan="4">Aucun projet Dolibarr synchronise pour le moment.</td></tr>
                <?php endif; ?>
                <?php foreach (($dolibarrProjectMappings ?? []) as $mapping): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($mapping['remote_title']) ?></strong>
                            <div class="table-cell-hint">#<?= htmlspecialchars((string) $mapping['dolibarr_project_id']) ?><?= !empty($mapping['remote_ref']) ? ' · ' . htmlspecialchars($mapping['remote_ref']) : '' ?></div>
                        </td>
                        <td><?= htmlspecialchars((string) ($mapping['remote_thirdparty'] ?? '')) ?: '-' ?></td>
                        <td><?= htmlspecialchars((string) ($mapping['local_project_name'] ?? 'Non mappe')) ?></td>
                        <td><?= htmlspecialchars((string) ($mapping['last_synced_at'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($activeSection === 'apis' && $canViewIntegrations): ?>
<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Configuration API reseaux</h2>
            <p class="panel-subtitle">Mode OAuth pour comptes centralises (Facebook, LinkedIn, Instagram) et mode direct user/mot de passe pour les autres reseaux.</p>
        </div>
    </div>

    <div class="info-banner" style="margin-bottom: 12px;">
        <strong>Documentation officielle par API</strong><br>
        <a class="link" href="https://developers.facebook.com/docs/" target="_blank" rel="noopener noreferrer">Facebook Graph API</a> ·
        <a class="link" href="https://learn.microsoft.com/linkedin/" target="_blank" rel="noopener noreferrer">LinkedIn Developer</a> ·
        <a class="link" href="https://developers.facebook.com/docs/instagram-api/" target="_blank" rel="noopener noreferrer">Instagram API</a> ·
        <a class="link" href="https://developers.tiktok.com/doc/" target="_blank" rel="noopener noreferrer">TikTok for Developers</a> ·
        <a class="link" href="https://developers.google.com/youtube" target="_blank" rel="noopener noreferrer">YouTube Data API</a> ·
        <a class="link" href="https://developers.facebook.com/docs/whatsapp/" target="_blank" rel="noopener noreferrer">WhatsApp Business Platform</a>
    </div>

    <div class="panel inset-panel" style="margin-bottom: 14px;">
        <h3>Logique multi-clients recommandee</h3>
        <p class="panel-subtitle">Comptes reseaux par client + autorisation OAuth centralisee. L etape suivante recommandee: gerer les liaisons compte-reseau directement sur la fiche client.</p>
        <ul class="requirement-list">
            <li class="requirement-item"><span class="requirement-state">1</span><span>Conserver ici les credentials globaux OAuth (apps Facebook/LinkedIn/Meta).</span></li>
            <li class="requirement-item"><span class="requirement-state">2</span><span>Ajouter sur chaque client ses comptes reseaux (TikTok, YouTube, Instagram, WhatsApp) et tokens associes.</span></li>
            <li class="requirement-item"><span class="requirement-state">3</span><span>Pour Facebook/LinkedIn: lier explicitement la page/organisation a chaque client via un mapping client-compte.</span></li>
            <li class="requirement-item"><span class="requirement-state">4</span><span>Lors de la publication, choisir le compte du client au lieu d un token global unique.</span></li>
        </ul>
    </div>

    <?php if ($canManageIntegrations): ?>
        <form method="post" class="form-grid">
            <input type="hidden" name="section" value="apis">
            <input type="hidden" name="settings_action" value="save_api_integrations">

            <label class="field"><span>Facebook mode</span><input type="text" name="facebook_mode" value="<?= htmlspecialchars((string) ($apiIntegrationsConfig['facebook']['mode'] ?? 'oauth')) ?>"></label>
            <label class="field"><span>Facebook app id</span><input type="text" name="facebook_app_id" value="<?= htmlspecialchars((string) ($apiIntegrationsConfig['facebook']['app_id'] ?? '')) ?>"></label>
            <label class="field"><span>Facebook app secret</span><input type="text" name="facebook_app_secret" value="<?= htmlspecialchars((string) ($apiIntegrationsConfig['facebook']['app_secret'] ?? '')) ?>"></label>
            <label class="field"><span>Facebook access token</span><input type="text" name="facebook_access_token" value="<?= htmlspecialchars((string) ($apiIntegrationsConfig['facebook']['access_token'] ?? '')) ?>"></label>

            <label class="field"><span>LinkedIn mode</span><input type="text" name="linkedin_mode" value="<?= htmlspecialchars((string) ($apiIntegrationsConfig['linkedin']['mode'] ?? 'oauth')) ?>"></label>
            <label class="field"><span>LinkedIn client id</span><input type="text" name="linkedin_client_id" value="<?= htmlspecialchars((string) ($apiIntegrationsConfig['linkedin']['client_id'] ?? '')) ?>"></label>
            <label class="field"><span>LinkedIn client secret</span><input type="text" name="linkedin_client_secret" value="<?= htmlspecialchars((string) ($apiIntegrationsConfig['linkedin']['client_secret'] ?? '')) ?>"></label>
            <label class="field"><span>LinkedIn access token</span><input type="text" name="linkedin_access_token" value="<?= htmlspecialchars((string) ($apiIntegrationsConfig['linkedin']['access_token'] ?? '')) ?>"></label>

            <label class="field"><span>Instagram mode</span><input type="text" name="instagram_mode" value="<?= htmlspecialchars((string) ($apiIntegrationsConfig['instagram']['mode'] ?? 'oauth')) ?>"></label>
            <label class="field"><span>Instagram access token</span><input type="text" name="instagram_access_token" value="<?= htmlspecialchars((string) ($apiIntegrationsConfig['instagram']['access_token'] ?? '')) ?>"></label>

            <label class="field"><span>TikTok mode</span><input type="text" name="tiktok_mode" value="<?= htmlspecialchars((string) ($apiIntegrationsConfig['tiktok']['mode'] ?? 'direct')) ?>"></label>
            <label class="field"><span>TikTok utilisateur</span><input type="text" name="tiktok_username" value="<?= htmlspecialchars((string) ($apiIntegrationsConfig['tiktok']['username'] ?? '')) ?>"></label>
            <label class="field"><span>TikTok mot de passe</span><input type="password" name="tiktok_password" value="<?= htmlspecialchars((string) ($apiIntegrationsConfig['tiktok']['password'] ?? '')) ?>"></label>

            <label class="field"><span>YouTube mode</span><input type="text" name="youtube_mode" value="<?= htmlspecialchars((string) ($apiIntegrationsConfig['youtube']['mode'] ?? 'direct')) ?>"></label>
            <label class="field"><span>YouTube utilisateur</span><input type="text" name="youtube_username" value="<?= htmlspecialchars((string) ($apiIntegrationsConfig['youtube']['username'] ?? '')) ?>"></label>
            <label class="field"><span>YouTube mot de passe</span><input type="password" name="youtube_password" value="<?= htmlspecialchars((string) ($apiIntegrationsConfig['youtube']['password'] ?? '')) ?>"></label>

            <label class="field"><span>WhatsApp mode</span><input type="text" name="whatsapp_mode" value="<?= htmlspecialchars((string) ($apiIntegrationsConfig['whatsapp']['mode'] ?? 'direct')) ?>"></label>
            <label class="field"><span>WhatsApp utilisateur</span><input type="text" name="whatsapp_username" value="<?= htmlspecialchars((string) ($apiIntegrationsConfig['whatsapp']['username'] ?? '')) ?>"></label>
            <label class="field"><span>WhatsApp mot de passe</span><input type="password" name="whatsapp_password" value="<?= htmlspecialchars((string) ($apiIntegrationsConfig['whatsapp']['password'] ?? '')) ?>"></label>

            <label class="field"><span>Webhook publication</span><input type="text" name="webhooks_publication" value="<?= htmlspecialchars((string) ($apiIntegrationsConfig['webhooks']['publication'] ?? '')) ?>"></label>
            <label class="field"><span>Webhook collecte KPI</span><input type="text" name="webhooks_kpi" value="<?= htmlspecialchars((string) ($apiIntegrationsConfig['webhooks']['kpi'] ?? '')) ?>"></label>

            <div class="form-actions">
                <button class="button" type="submit">Enregistrer la configuration API</button>
            </div>
        </form>
    <?php else: ?>
        <div class="info-banner">Lecture seule: la configuration API est reservee aux profils autorises.</div>
    <?php endif; ?>

    <details class="panel inset-panel collapsible-panel" open>
        <summary class="collapsible-summary">
            <span>
                <strong>Logs API</strong>
                <small>Succes, echecs et reponses des integrations</small>
            </span>
            <span class="collapsible-indicator">Afficher / masquer</span>
        </summary>
        <form method="get" action="<?= htmlspecialchars(route_url('/settings')) ?>" class="list-toolbar">
            <input type="hidden" name="section" value="apis">
            <label class="field toolbar-field">
                <span>Statut</span>
                <select name="api_status">
                    <option value="" <?= (($apiLogFilters['status'] ?? '') === '') ? 'selected' : '' ?>>Tous</option>
                    <option value="success" <?= (($apiLogFilters['status'] ?? '') === 'success') ? 'selected' : '' ?>>Succes</option>
                    <option value="failure" <?= (($apiLogFilters['status'] ?? '') === 'failure') ? 'selected' : '' ?>>Echec</option>
                </select>
            </label>
            <label class="field toolbar-field">
                <span>Type</span>
                <select name="api_type">
                    <option value="" <?= (($apiLogFilters['type'] ?? '') === '') ? 'selected' : '' ?>>Tous</option>
                    <option value="publication" <?= (($apiLogFilters['type'] ?? '') === 'publication') ? 'selected' : '' ?>>Publication</option>
                    <option value="kpi" <?= (($apiLogFilters['type'] ?? '') === 'kpi') ? 'selected' : '' ?>>Collecte KPI</option>
                </select>
            </label>
            <label class="field toolbar-field"><span>Du</span><input type="date" name="api_from" value="<?= htmlspecialchars((string) ($apiLogFilters['from'] ?? '')) ?>"></label>
            <label class="field toolbar-field"><span>Au</span><input type="date" name="api_to" value="<?= htmlspecialchars((string) ($apiLogFilters['to'] ?? '')) ?>"></label>
            <div class="toolbar-actions">
                <button class="button" type="submit">Filtrer</button>
                <a class="button secondary" href="<?= htmlspecialchars(settings_section_url('apis')) ?>">Reset</a>
            </div>
        </form>
        <div class="table-wrap compact-table">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Projet</th>
                        <th>Integration</th>
                        <th>Evenement</th>
                        <th>Statut</th>
                        <th>HTTP</th>
                        <th>Erreur</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($apiEventLogs ?? []) as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($log['created_at'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($log['client_nom'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($log['projet_nom'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($log['integration'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($log['event_type'] ?? '')) ?></td>
                            <td><?= !empty($log['success']) ? '<span class="status-badge status-terminee">Succes</span>' : '<span class="status-badge status-annulee">Echec</span>' ?></td>
                            <td><?= htmlspecialchars((string) ($log['status_code'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars((string) ($log['error_message'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($apiEventLogs)): ?>
                        <tr><td colspan="8">Aucun log API.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php $apiPage = (int) ($apiPage ?? 1); $apiLogsTotalPages = (int) ($apiLogsTotalPages ?? 1); ?>
        <?php if ($apiLogsTotalPages > 1): ?>
            <div class="pagination">
                <?php if ($apiPage > 1): ?>
                    <a class="button secondary" href="<?= htmlspecialchars(settings_section_url('apis', array_merge($_GET, ['section' => 'apis', 'api_page' => $apiPage - 1]))) ?>">← Precedent</a>
                <?php endif; ?>
                <span class="pagination-info">Page <?= $apiPage ?> / <?= $apiLogsTotalPages ?></span>
                <?php if ($apiPage < $apiLogsTotalPages): ?>
                    <a class="button secondary" href="<?= htmlspecialchars(settings_section_url('apis', array_merge($_GET, ['section' => 'apis', 'api_page' => $apiPage + 1]))) ?>">Suivant →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </details>
</section>
<?php endif; ?>

 </main>
</div>
<script data-settings-navigation-script>
(()=>{
 const root=document.querySelector('.settings-secondary-sidebar'); if(!root)return;
 const input=root.querySelector('[data-settings-search]'),empty=root.querySelector('[data-settings-empty]');
 const groups=[...root.querySelectorAll('.settings-sidebar-group')],cards=[...root.querySelectorAll('[data-settings-section]')];
 const storageKey='jaxe.settings.open-groups.v1';
 try{const saved=JSON.parse(localStorage.getItem(storageKey)||'[]');groups.forEach((group,index)=>group.open=saved.includes(index));}catch(error){}
 groups.forEach((group,index)=>group.addEventListener('toggle',()=>{if(input&&input.value.trim())return;try{localStorage.setItem(storageKey,JSON.stringify(groups.map((item,i)=>item.open?i:null).filter(i=>i!==null)))}catch(error){}}));
 const filter=()=>{const query=(input?.value||'').trim().toLocaleLowerCase();let visible=0;cards.forEach(card=>{const match=!query||card.textContent.toLocaleLowerCase().includes(query);card.hidden=!match;if(match)visible++});groups.forEach(group=>{const has=[...group.querySelectorAll('[data-settings-section]')].some(card=>!card.hidden);group.hidden=!has;if(query&&has)group.open=true});if(empty)empty.hidden=visible>0};
 input?.addEventListener('input',filter);
 document.addEventListener('keydown',event=>{if((event.ctrlKey||event.metaKey)&&event.key.toLocaleLowerCase()==='k'){event.preventDefault();input?.focus();input?.select()}if(event.key==='Escape'&&document.activeElement===input){input.value='';filter();input.blur()}});
 cards.forEach(card=>card.addEventListener('click',()=>{try{sessionStorage.setItem('jaxe.settings.last-section',card.dataset.settingsSection||'')}catch(error){}}));
})();
</script>