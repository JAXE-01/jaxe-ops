<?php
function home_task_status_label($taskType, $status) {
    $taskType = (string) $taskType;
    $status = (string) $status;
    if (in_array($taskType, ['Montage', 'Production', 'Brief', 'Script', 'Calendrier'], true) && $status === 'Annulee') {
        return 'Non valide';
    }
    return $status;
}
?>
<?php if (!empty($isScopedDashboard)): ?>
        <section class="dashboard-dual-row span-2">
            <section class="panel compact-panel tone-info dashboard-half-panel">
                <h2>Echeances proches</h2>
                <div class="task-list compact-scroll compact-info">
                    <?php foreach ($upcomingDeadlines as $task): ?>
                        <a class="task-item slim pipeline-item" href="<?= htmlspecialchars(route_url('/calendrier/task/' . $task['id'])) ?>">
                            <strong><?= htmlspecialchars($task['titre']) ?></strong>
                            <span><?= htmlspecialchars($task['entreprise']) ?> · <?= htmlspecialchars($task['projet_nom']) ?></span>
                            <?php $displayStatus = home_task_status_label((string) ($task['type_tache'] ?? ''), (string) ($task['statut'] ?? '')); ?>
                            <span><?= htmlspecialchars($task['type_tache']) ?> · <span class="status-badge status-<?= htmlspecialchars(strtolower(str_replace(' ', '-', (string) ($task['statut'] ?? '')))) ?>"><?= htmlspecialchars($displayStatus) ?></span></span>
                            <span>Deadline: <?= htmlspecialchars($task['deadline']) ?><?= $task['auteur'] ? ' · ' . htmlspecialchars($task['auteur']) : '' ?></span>
                        </a>
                    <?php endforeach; ?>
                    <?php if (empty($upcomingDeadlines)): ?>
                        <p>Aucune echeance immediate.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="panel compact-panel tone-danger dashboard-half-panel">
                <h2>Retards</h2>
                <div class="task-list compact-scroll compact-danger">
                    <?php foreach ($delayedTasks as $task): ?>
                        <a class="task-item slim delayed" href="<?= htmlspecialchars(route_url('/calendrier/task/' . $task['id'])) ?>">
                            <strong><?= htmlspecialchars($task['titre']) ?></strong>
                            <span><?= htmlspecialchars($task['entreprise']) ?> · <?= htmlspecialchars($task['projet_nom']) ?></span>
                            <span>Deadline depassee le <?= htmlspecialchars($task['deadline']) ?></span>
                        </a>
                    <?php endforeach; ?>
                    <?php if (empty($delayedTasks)): ?>
                        <p>Aucun retard detecte.</p>
                    <?php endif; ?>
                </div>
            </section>
        </section>

        <section class="panel span-2">
            <h2>Mes plans du mois</h2>
            <div class="chips-row">
                <?php foreach ($projectsByType as $row): ?>
                    <span class="chip"><?= htmlspecialchars($row['type_projet']) ?>: <?= htmlspecialchars((string) $row['total']) ?></span>
                <?php endforeach; ?>
            </div>
            <div class="table-wrap compact-table">
                <table>
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Projet</th>
                            <th>Mois</th>
                            <th>Videos</th>
                            <th>Visuels</th>
                            <th>Total</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($currentMonthPlans as $plan): ?>
                            <tr>
                                <td><?= htmlspecialchars($plan['entreprise']) ?></td>
                                <td><?= htmlspecialchars($plan['projet_nom']) ?></td>
                                <td><?= htmlspecialchars(date('m/Y', strtotime($plan['periode_mois']))) ?></td>
                                <td><?= htmlspecialchars($plan['videos_livres'] . '/' . $plan['videos_prevus']) ?></td>
                                <td><?= htmlspecialchars($plan['visuels_livres'] . '/' . $plan['visuels_prevus']) ?></td>
                                <td><?= htmlspecialchars($plan['livrables_livres'] . '/' . $plan['livrables_prevus']) ?></td>
                                <td><span class="status-badge status-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $plan['statut']))) ?>"><?= htmlspecialchars($plan['statut']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($currentMonthPlans)): ?>
                            <tr><td colspan="7">Aucun plan mensuel lie a vos taches ce mois-ci.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </section>
<?php else: ?>
    <section class="stats-grid dashboard-stats">
        <article class="stat-card emphasis">
            <span class="stat-label">Projets actifs</span>
            <strong class="stat-value"><?= htmlspecialchars((string) $overview['projets']) ?></strong>
            <span class="stat-link">Abonnements: <?= htmlspecialchars((string) $overview['abonnements']) ?> · SEA: <?= htmlspecialchars((string) $overview['sea']) ?></span>
        </article>
        <article class="stat-card">
            <span class="stat-label">Taches a faire</span>
            <strong class="stat-value"><?= htmlspecialchars((string) $overview['taches_a_faire']) ?></strong>
            <span class="stat-link">Charge immediate</span>
        </article>
        <article class="stat-card warning-card">
            <span class="stat-label">Taches en retard</span>
            <strong class="stat-value"><?= htmlspecialchars((string) $overview['taches_en_retard']) ?></strong>
            <span class="stat-link">A traiter en priorite</span>
        </article>
        <article class="stat-card">
            <span class="stat-label">Clients suivis</span>
            <strong class="stat-value"><?= htmlspecialchars((string) $overview['clients']) ?></strong>
            <span class="stat-link">Portefeuille actif</span>
        </article>
    </section>

    <section class="dashboard-grid">
        <section class="panel span-2">
            <div class="panel-head">
                <h2>Pilotage des projets</h2>
                <a class="button" href="<?= htmlspecialchars(route_url('/projet/create')) ?>">Nouveau projet</a>
            </div>
            <div class="chips-row">
                <?php foreach ($projectsByType as $row): ?>
                    <span class="chip"><?= htmlspecialchars($row['type_projet']) ?>: <?= htmlspecialchars((string) $row['total']) ?></span>
                <?php endforeach; ?>
            </div>
            <div class="table-wrap compact-table">
                <table>
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Projet</th>
                            <th>Mois</th>
                            <th>Videos</th>
                            <th>Visuels</th>
                            <th>Total</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($currentMonthPlans as $plan): ?>
                            <tr>
                                <td><?= htmlspecialchars($plan['entreprise']) ?></td>
                                <td><?= htmlspecialchars($plan['projet_nom']) ?></td>
                                <td><?= htmlspecialchars(date('m/Y', strtotime($plan['periode_mois']))) ?></td>
                                <td><?= htmlspecialchars($plan['videos_livres'] . '/' . $plan['videos_prevus']) ?></td>
                                <td><?= htmlspecialchars($plan['visuels_livres'] . '/' . $plan['visuels_prevus']) ?></td>
                                <td><?= htmlspecialchars($plan['livrables_livres'] . '/' . $plan['livrables_prevus']) ?></td>
                                <td><span class="status-badge status-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $plan['statut']))) ?>"><?= htmlspecialchars($plan['statut']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($currentMonthPlans)): ?>
                            <tr><td colspan="7">Aucun plan mensuel pour le mois en cours.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="dashboard-dual-row span-2">
            <section class="panel compact-panel tone-info dashboard-half-panel">
                <h2>Echeances proches</h2>
                <div class="task-list compact-scroll compact-info">
                    <?php foreach ($upcomingDeadlines as $task): ?>
                        <a class="task-item slim pipeline-item" href="<?= htmlspecialchars(route_url('/calendrier/task/' . $task['id'])) ?>">
                            <strong><?= htmlspecialchars($task['titre']) ?></strong>
                            <span><?= htmlspecialchars($task['entreprise']) ?> · <?= htmlspecialchars($task['projet_nom']) ?></span>
                            <?php $displayStatus = home_task_status_label((string) ($task['type_tache'] ?? ''), (string) ($task['statut'] ?? '')); ?>
                            <span><?= htmlspecialchars($task['type_tache']) ?> · <span class="status-badge status-<?= htmlspecialchars(strtolower(str_replace(' ', '-', (string) ($task['statut'] ?? '')))) ?>"><?= htmlspecialchars($displayStatus) ?></span></span>
                            <span>Deadline: <?= htmlspecialchars($task['deadline']) ?><?= $task['auteur'] ? ' · ' . htmlspecialchars($task['auteur']) : '' ?></span>
                        </a>
                    <?php endforeach; ?>
                    <?php if (empty($upcomingDeadlines)): ?>
                        <p>Aucune echeance immediate.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="panel compact-panel tone-danger dashboard-half-panel">
                <h2>Retards</h2>
                <div class="task-list compact-scroll compact-danger">
                    <?php foreach ($delayedTasks as $task): ?>
                        <a class="task-item slim delayed" href="<?= htmlspecialchars(route_url('/calendrier/task/' . $task['id'])) ?>">
                            <strong><?= htmlspecialchars($task['titre']) ?></strong>
                            <span><?= htmlspecialchars($task['entreprise']) ?> · <?= htmlspecialchars($task['projet_nom']) ?></span>
                            <span>Deadline depassee le <?= htmlspecialchars($task['deadline']) ?></span>
                        </a>
                    <?php endforeach; ?>
                    <?php if (empty($delayedTasks)): ?>
                        <p>Aucun retard detecte.</p>
                    <?php endif; ?>
                </div>
            </section>
        </section>
    </section>
<?php endif; ?>
