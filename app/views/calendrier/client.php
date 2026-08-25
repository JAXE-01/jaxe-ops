<section class="panel">
    <div class="panel-head">
        <div>
            <h2><?= htmlspecialchars($client['entreprise']) ?></h2>
            <p class="panel-subtitle">Choisis un projet pour ouvrir le calendrier mensuel, les briefs et les livrables lies.</p>
        </div>
        <a class="button secondary" href="<?= htmlspecialchars(route_url('/calendrier')) ?>">Retour aux clients</a>
    </div>
    <div class="table-wrap compact-table">
        <table>
            <thead>
                <tr>
                    <th>Projet</th>
                    <th>Type</th>
                    <th>Periode projet</th>
                    <th>Quota</th>
                    <th>Plans</th>
                    <th>Taches terminees</th>
                    <th>Taches restantes</th>
                    <th>Stats calendrier</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $project): ?>
                    <?php $stats = (array) ($projectCalendarStats[(int) ($project['id'] ?? 0)] ?? []); ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($project['nom']) ?></strong>
                            <?php if (!empty($project['campagne_nom'])): ?>
                                <div class="mini-text">Campagne: <?= htmlspecialchars($project['campagne_nom']) ?></div>
                            <?php endif; ?>
                            <div class="mini-text">Charge de communication: <?= htmlspecialchars($project['charge_compte_nom'] ?: 'Non assigne') ?></div>
                            <div class="mini-text">Charge de clientele: <?= htmlspecialchars($project['charge_clientele_nom'] ?: 'Non assigne') ?></div>
                            <div class="mini-text">Cadreur: <?= htmlspecialchars($project['cadreur_nom'] ?: 'Non assigne') ?></div>
                            <div class="mini-text">Videaste: <?= htmlspecialchars($project['videaste_nom'] ?: 'Non assigne') ?></div>
                            <div class="mini-text">Designer: <?= htmlspecialchars($project['designer_nom'] ?: 'Non assigne') ?></div>
                        </td>
                        <td><?= htmlspecialchars($project['type_projet']) ?></td>
                        <td><?= htmlspecialchars($project['date_debut']) ?> → <?= htmlspecialchars($project['date_fin']) ?></td>
                        <td><?= htmlspecialchars((string) $project['quota_videos_mensuel']) ?> video(s) / <?= htmlspecialchars((string) $project['quota_visuels_mensuel']) ?> visuel(x)</td>
                        <td><?= htmlspecialchars((string) $project['plans_total']) ?></td>
                        <td><?= htmlspecialchars((string) $project['taches_terminees']) ?></td>
                        <td><?= htmlspecialchars((string) $project['taches_restantes']) ?></td>
                        <td>
                            <div class="mini-text">Completion: <?= htmlspecialchars((string) ($stats['calendar_completion_rate'] ?? 0)) ?>%</div>
                            <div class="mini-text">Retard moyen: <?= htmlspecialchars((string) ($stats['avg_delay_days'] ?? 0)) ?> j</div>
                            <div class="mini-text">1er passage: <?= htmlspecialchars((string) ($stats['first_pass_validation_rate'] ?? 0)) ?>%</div>
                            <div class="mini-text">Invalidations: <?= htmlspecialchars((string) ($stats['invalidation_ratio'] ?? 0)) ?>%</div>
                        </td>
                        <td>
                            <div class="mini-text"><span class="status-badge status-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $project['statut']))) ?>"><?= htmlspecialchars($project['statut']) ?></span></div>
                            <a class="button" href="<?= htmlspecialchars(route_url('/calendrier/projet/' . $project['id'])) ?>">Ouvrir le calendrier</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($projects)): ?>
                    <tr><td colspan="9">Aucun projet pour ce client.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
