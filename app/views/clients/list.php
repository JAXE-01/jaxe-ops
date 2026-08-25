<?php
$clients = is_array($clients ?? null) ? $clients : [];
$activeClients = array_filter($clients, static fn($client) => strtolower((string) ($client['statut'] ?? '')) === 'actif');
$inactiveClients = count($clients) - count($activeClients);
?>
<section class="page-intro-card">
 <div><span class="page-eyebrow">Portefeuille clients</span><h2>Clients</h2><p>Centralisez les coordonnées, le statut et les projets de chaque client suivi.</p></div>
 <a class="button primary" href="<?= htmlspecialchars(route_url('/client/create')) ?>">+ Nouveau client</a>
</section>
<div class="entity-stats-grid">
 <article class="entity-stat"><span>Total clients</span><strong><?= count($clients) ?></strong><small>Portefeuille enregistré</small></article>
 <article class="entity-stat"><span>Clients actifs</span><strong><?= count($activeClients) ?></strong><small>Collaboration en cours</small></article>
 <article class="entity-stat"><span>Clients inactifs</span><strong><?= $inactiveClients ?></strong><small>À réactiver ou archiver</small></article>
</div>
<section class="panel entity-list-panel">
 <div class="panel-head"><div><h2>Répertoire client</h2><p class="panel-subtitle">Retrouvez rapidement les informations de contact et l’état de chaque relation.</p></div><span class="chip"><?= count($clients) ?> résultat<?= count($clients) > 1 ? 's' : '' ?></span></div>
 <div class="table-wrap"><table class="data-table entity-table"><thead><tr><th>Client</th><th>Entreprise</th><th>Secteur</th><th>Coordonnées</th><th>Statut</th><th class="table-actions-head">Actions</th></tr></thead><tbody>
 <?php foreach ($clients as $client): ?><tr>
  <td><div class="entity-identity"><span class="entity-avatar"><?= htmlspecialchars(strtoupper(substr((string) ($client['nom'] ?? '?'), 0, 1))) ?></span><span><strong><?= htmlspecialchars($client['nom'] ?? '') ?></strong><small>Client #<?= (int) ($client['id'] ?? 0) ?></small></span></div></td>
  <td><?= htmlspecialchars($client['entreprise'] ?: '—') ?></td><td><?= htmlspecialchars($client['secteur'] ?: '—') ?></td>
  <td><div class="entity-contact"><span><?= htmlspecialchars($client['email'] ?: 'Email non renseigné') ?></span><small><?= htmlspecialchars($client['telephone'] ?: 'Téléphone non renseigné') ?></small></div></td>
  <td><span class="status-badge <?= strtolower((string) ($client['statut'] ?? '')) === 'actif' ? 'status-terminee' : 'status-brouillon' ?>"><?= htmlspecialchars($client['statut'] ?? 'Inconnu') ?></span></td>
  <td><div class="row-actions"><a class="button secondary button-small" href="<?= htmlspecialchars(route_url('/client/edit/' . (int) $client['id'])) ?>">Modifier</a><a class="button danger button-small" href="<?= htmlspecialchars(route_url('/client/delete/' . (int) $client['id'])) ?>" onclick="return confirm('Supprimer ce client ?');">Supprimer</a></div></td>
 </tr><?php endforeach; ?>
 <?php if (empty($clients)): ?><tr><td colspan="6"><div class="empty-state"><strong>Aucun client enregistré</strong><span>Créez votre premier client pour démarrer un projet.</span></div></td></tr><?php endif; ?>
 </tbody></table></div>
</section>