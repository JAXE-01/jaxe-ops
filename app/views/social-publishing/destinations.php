<?php
$groups=[];
foreach($connections as$destination){
    if($destination['status']==='Revoked')continue;
    $key=(int)$destination['client_id'];
    $groups[$key]['name']=$destination['client_name']?:'Sans client';
    $groups[$key]['items'][]=$destination;
}
uasort($groups,static fn($a,$b)=>strcasecmp($a['name'],$b['name']));
?>
<input type="search" class="destination-search" placeholder="Rechercher un compte ou un client" aria-label="Rechercher une destination" data-destination-search>
<p><small>Les comptes sont rangés par client. Gérez leurs projets depuis la fiche projet.</small></p>
<?php foreach($groups as$group): ?>
<details class="connection-group"><summary><?= $e($group['name']) ?> <small>· <?= count($group['items']) ?> compte(s)</small></summary>
<div class="connection-list">
<?php foreach($group['items'] as$c):
$scopes=(array)json_decode((string)($c['scopes_json']??'[]'),true);
$networkOnly=in_array($c['provider'],['tiktok','linkedin','youtube'],true);
$ready=!$networkOnly&&!empty($c['last_validated_at'])&&($c['provider']==='facebook'?in_array('pages_manage_posts',$scopes,true):(bool)array_intersect(['instagram_content_publish','instagram_business_content_publish'],$scopes));
?>
<article data-destination-searchable="<?= $e(mb_strtolower($group['name'].' '.$c['account_label'])) ?>">
<?= SocialNetworkIcon::render((string)$c['provider']) ?>
<div><strong><?= $e($c['account_label']) ?></strong><small><?= $e($providers[$c['provider']]??$c['provider']) ?> · <?= count(array_filter(explode(',',(string)($c['project_ids']??'')))) ?> projet(s) associé(s)</small>
<?php if($canManage): ?><a href="<?= $e(route_url('/social-oauth/connect/'.(int)$c['id'])) ?>" target="_blank" rel="noopener noreferrer"><?= $c['status']==='Connected'?'Actualiser les droits':'Connecter' ?> <?= $e($networkOnly?($providers[$c['provider']]??$c['provider']):'Meta') ?> ↗</a>
<?php if($networkOnly&&!empty($c['refresh_token_encrypted'])): ?><form method="post" action="<?= $e(route_url('/network-oauth/renew/'.(int)$c['id'])) ?>"><button type="submit" class="button secondary">Renouveler l’accès</button></form><?php endif ?>
<?php endif ?>
<?php if($networkOnly): ?><small>Connexion OAuth uniquement · collecte et publication non activées<?= $c['provider']==='linkedin'?' · profil personnel, pas les Pages entreprise':'' ?></small><?php endif ?>
<?php if(!empty($managePage)&&$canManage): ?><details class="connection-edit"><summary>Modifier</summary><form method="post"><input type="hidden" name="action" value="update"><input type="hidden" name="connection_id" value="<?= (int)$c['id'] ?>"><label>Nom<input name="account_label" value="<?= $e($c['account_label']) ?>" required maxlength="190"></label><label>Client<select name="client_id" required><?php foreach($clients as$client): ?><option value="<?= (int)$client['id'] ?>" <?= (int)$client['id']===(int)$c['client_id']?'selected':'' ?>><?= $e($client['name']) ?></option><?php endforeach ?></select></label><button class="button secondary" type="submit">Enregistrer</button></form><form method="post" onsubmit="return confirm('Retirer ce compte social ? Son historique publié sera conservé.')"><input type="hidden" name="action" value="remove"><input type="hidden" name="connection_id" value="<?= (int)$c['id'] ?>"><button class="button danger-soft" type="submit">Retirer</button></form></details><?php endif ?>
</div><span class="connection-state"><?= $e($c['status']==='Connected'?($networkOnly?'Compte connecté':($ready?'Prête':'Droits incomplets')):'À connecter') ?></span>
</article>
<?php endforeach ?></div></details>
<?php endforeach ?>
<?php if($canManage): ?>
<details class="connect-form"><summary>+ Connecter TikTok, LinkedIn ou YouTube</summary>
<form method="post" action="<?= $e(route_url(!empty($managePage)?'/social-connection':'/social-publishing')) ?>">
<input type="hidden" name="action" value="<?= !empty($managePage)?'create':'connection' ?>">
<label>Réseau<select name="provider" required><option value="tiktok">TikTok</option><option value="linkedin">LinkedIn · profil</option><option value="youtube">YouTube · chaîne</option></select></label>
<label>Client<select name="client_id" required><option value="">Sélectionner</option><?php foreach($clients as$client): ?><option value="<?= (int)$client['id'] ?>"><?= $e($client['name']) ?></option><?php endforeach ?></select></label>
<label>Libellé interne<input name="account_label" required placeholder="Compte à connecter"></label>
<button class="button primary" type="submit">Préparer la connexion</button>
<small>Après création, cliquez sur Connecter. LinkedIn nécessite le produit Sign In with LinkedIn using OpenID Connect.</small>
</form></details>
<?php endif ?>
<script>
document.querySelector('[data-destination-search]')?.addEventListener('input',function(){const q=this.value.trim().toLocaleLowerCase();document.querySelectorAll('.connection-group').forEach(group=>{let visible=0;group.querySelectorAll('[data-destination-searchable]').forEach(row=>{row.hidden=!row.dataset.destinationSearchable.includes(q);if(!row.hidden)visible++;});group.hidden=!visible;if(q)group.open=visible>0;});});
</script>
