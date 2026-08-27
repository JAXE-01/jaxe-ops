<?php
// Assignment data is restricted to the current tenant; foreign tenants are never exposed.
$stmt=Database::getConnection()->prepare('SELECT id,provider,external_account_id,client_id FROM social_connections WHERE tenant_id=:tenant AND external_account_id IS NOT NULL');
$stmt->execute(['tenant'=>TenantGuard::tenantId()]);$assigned=[];
foreach($stmt->fetchAll(PDO::FETCH_ASSOC)as$row)$assigned[$row['provider'].':'.$row['external_account_id']]=$row;
?>
<label class="meta-check"><input type="checkbox" data-show-assigned> Afficher les pages déjà associées à ce client (actualisation des droits)</label>
<p>Les pages rattachées à un autre client ne sont pas proposées. Les pages déjà associées à ce client sont masquées par défaut.</p>
<div class="meta-selection-list">
<?php foreach($pages as$index=>$page):foreach(['facebook'=>$page,'instagram'=>$page['instagram_business_account']??null]as$provider=>$item):
if(!is_array($item)||empty($item['id']))continue;
$owner=$assigned[$provider.':'.$item['id']]??null;
if($owner&&(int)$owner['client_id']!==(int)$connection['client_id'])continue;
$selected=[];
if($owner){$mapping=Database::getConnection()->prepare('SELECT project_id FROM social_connection_projects WHERE connection_id=:id AND tenant_id=:tenant');$mapping->execute(['id'=>$owner['id'],'tenant'=>TenantGuard::tenantId()]);$selected=array_map('intval',$mapping->fetchAll(PDO::FETCH_COLUMN));}
?>
<article class="meta-selection-card" <?= $owner?'hidden data-already-assigned':'' ?>>
<div class="meta-selection-heading"><strong><?= $e($item['username']??$item['name']??$page['name']??'Page') ?></strong><small><?= $e(ucfirst($provider)) ?><?= $owner?' · Déjà associée':'' ?></small></div>
<label class="meta-check"><input type="checkbox" name="destinations[]" value="<?= $provider ?>:<?= (int)$index ?>" <?= $owner?'disabled':'' ?>> <?= $owner?'Actualiser cette destination':'Associer cette destination au client' ?></label>
<fieldset class="meta-projects"><legend>Projets autorisés</legend><small>Sans sélection : page connectée au client, mais aucune publication autorisée avant son association à un projet.</small>
<?php foreach($projects as$project):?><label><input type="checkbox" name="projects[<?= $provider ?>_<?= (int)$index ?>][]" value="<?= (int)$project['id'] ?>" <?= in_array((int)$project['id'],$selected,true)?'checked':'' ?> <?= $owner?'disabled':'' ?>> <?= $e($project['nom']) ?></label><?php endforeach ?>
<?php if(!$projects):?><small>Créez un projet pour associer cette page ensuite.</small><?php endif ?>
</fieldset></article>
<?php endforeach;endforeach ?>
</div>
<script>
document.querySelector('[data-show-assigned]')?.addEventListener('change',function(){document.querySelectorAll('[data-already-assigned]').forEach(card=>{card.hidden=!this.checked;card.querySelectorAll('input').forEach(input=>{input.disabled=card.hidden;if(card.hidden&&input.name==='destinations[]')input.checked=false;});});});
</script>
