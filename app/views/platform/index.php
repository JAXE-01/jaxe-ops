<?php $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); ?>
<section class="panel">
  <h2>Comptes de la plateforme</h2>
  <p>Supervision SaaS distincte de la production de l’agence Jaxe. Cette vue est en lecture seule : elle ne vous connecte pas à la place d’un client.</p>
  <div style="overflow-x:auto"><table><thead><tr><th>Entreprise / espace</th><th>État</th><th>Offre</th><th>Organisations</th><th>Utilisateurs</th><th>Diagnostic</th></tr></thead><tbody>
  <?php foreach($tenants as$t): ?><tr><td><?= $e($t['name']) ?></td><td><?= $e($t['status']) ?></td><td><?= $e($t['plan_code']) ?></td><td><?= (int)$t['organization_count'] ?></td><td><?= (int)$t['member_count'] ?></td><td><a href="<?= $e(route_url('/platform').'?tenant_id='.(int)$t['id']) ?>">Consulter les accès</a></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>
<?php if($selected): ?><section class="panel"><h2>Diagnostic des accès · espace #<?= $selected ?></h2>
<div style="overflow-x:auto"><table><thead><tr><th>Utilisateur</th><th>E-mail</th><th>Organisation</th><th>Rôle dans l’espace</th><th>Adhésion</th><th>Compte</th></tr></thead><tbody>
<?php foreach($members as$m): ?><tr><td><?= $e($m['nom']) ?></td><td><?= $e($m['email']) ?></td><td><?= $e($m['organization_name']) ?></td><td><?= $e($m['membership_role']) ?></td><td><?= $e($m['membership_status']) ?></td><td><?= $e($m['statut']) ?></td></tr><?php endforeach; ?>
<?php if(!$members): ?><tr><td colspan="6">Aucun utilisateur dans cet espace.</td></tr><?php endif; ?>
</tbody></table></div></section><?php endif; ?>
<section class="panel"><h2>Assistance client</h2><p>Le changement de droits et l’accès temporaire à l’espace d’un client nécessitent un parcours support dédié, avec motif, durée limitée et journal d’activité. Aucun accès par usurpation de session n’est activé ici.</p></section>
