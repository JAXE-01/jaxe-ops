<?php $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');$managePage=true; ?>
<link rel="stylesheet" href="<?= $e(app_url('/public/assets/social-publishing.css?v=7')) ?>">
<section class="social-account-hero">
 <div><span class="publish-kicker">CONFIGURATION</span><h1>Comptes sociaux</h1><p>Connectez, renommez ou retirez les comptes. Les publications restent dans un espace séparé.</p></div>
 <a class="button primary" href="<?= $e(route_url('/social-publishing')) ?>">Ouvrir les publications →</a>
</section>
<section class="panel social-account-panel">
 <div class="section-head"><div><h2>Destinations</h2><p>Classées par client et par réseau.</p></div><span class="status-pill"><?= count($connections) ?> compte(s)</span></div>
 <?php require dirname(__DIR__).'/social-publishing/destinations.php'; ?>
 <?php if($canManage): ?><details class="connect-form"><summary>+ Connecter Meta à un client</summary><form method="post"><input type="hidden" name="action" value="create"><input type="hidden" name="provider" value="facebook"><label>Client<select name="client_id" required><option value="">Sélectionner</option><?php foreach($clients as$c):?><option value="<?= (int)$c['id']?>"><?= $e($c['name'])?></option><?php endforeach?></select></label><label>Libellé interne<input name="account_label" required placeholder="Connexion Meta"></label><button class="button primary" type="submit">Préparer Meta</button><small>Facebook et les comptes Instagram liés seront proposés pendant OAuth.</small></form></details><?php endif ?>
</section>
<script src="<?= $e(app_url('/public/assets/social-connections.js?v=2')) ?>"></script>
