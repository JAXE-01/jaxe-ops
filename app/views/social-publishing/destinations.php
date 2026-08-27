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
<input type="search" class="destination-search" placeholder="Rechercher une page ou un client" aria-label="Rechercher une destination" data-destination-search>
<p><small>Les pages associées sont rangées par client. Gérez leurs projets depuis la fiche projet.</small></p>
<?php foreach($groups as$group): ?>
<details class="connection-group"><summary><?= $e($group['name']) ?> <small>· <?= count($group['items']) ?> page(s)</small></summary>
<div class="connection-list">
<?php foreach($group['items'] as$c):
$scopes=(array)json_decode((string)($c['scopes_json']??'[]'),true);
$ready=!empty($c['last_validated_at'])&&($c['provider']==='facebook'?in_array('pages_manage_posts',$scopes,true):(bool)array_intersect(['instagram_content_publish','instagram_business_content_publish'],$scopes));
?>
<article data-destination-searchable="<?= $e(mb_strtolower($group['name'].' '.$c['account_label'])) ?>">
<span class="network-logo"><?= $e(strtoupper(substr($c['provider'],0,1))) ?></span>
<div><strong><?= $e($c['account_label']) ?></strong><small><?= $e($providers[$c['provider']]??$c['provider']) ?> · <?= count(array_filter(explode(',',(string)($c['project_ids']??'')))) ?> projet(s) associé(s)</small>
<?php if($canManage): ?><a href="<?= $e(route_url('/social-oauth/connect/'.(int)$c['id'])) ?>" target="_blank" rel="noopener"><?= $c['status']==='Connected'?'Actualiser les droits Meta':'Connecter Meta' ?> ↗</a><?php endif ?>
</div><span class="connection-state"><?= $e($c['status']==='Connected'?($ready?'Prête':'Droits incomplets'):'À connecter') ?></span>
</article>
<?php endforeach ?></div></details>
<?php endforeach ?>
<script>
document.querySelector('[data-destination-search]')?.addEventListener('input',function(){const q=this.value.trim().toLocaleLowerCase();document.querySelectorAll('.connection-group').forEach(group=>{let visible=0;group.querySelectorAll('[data-destination-searchable]').forEach(row=>{row.hidden=!row.dataset.destinationSearchable.includes(q);if(!row.hidden)visible++;});group.hidden=!visible;if(q)group.open=visible>0;});});
</script>
