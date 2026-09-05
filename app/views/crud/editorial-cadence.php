<?php
$cadenceRows=$_POST['cadence']??CadenceRevision::latest((string)($record['publication_rules']??''));
$cadenceRows=array_values(array_filter((array)$cadenceRows,static fn($row)=>is_array($row)&&(!empty($row['active'])||isset($row['day']))));
$days=[1=>'Lundi',2=>'Mardi',3=>'Mercredi',4=>'Jeudi',5=>'Vendredi',6=>'Samedi',7=>'Dimanche'];
$renderCadenceRow=static function(array$r,int$i)use($days){$frequency=(string)($r['frequency']??(((int)($r['every']??1)===2)?'biweekly':'weekly')); ?>
<article class="cadence-row" data-cadence-row>
 <input type="hidden" name="cadence[<?= $i ?>][active]" value="1">
 <span class="cadence-row-number" data-cadence-number><?= $i+1 ?></span>
 <label><span>Jour</span><select name="cadence[<?= $i ?>][day]" required><?php foreach($days as$d=>$label): ?><option value="<?= $d ?>" <?= (int)($r['day']??1)===$d?'selected':'' ?>><?= $label ?></option><?php endforeach ?></select></label>
 <label><span>Heure</span><input type="time" name="cadence[<?= $i ?>][time]" value="<?= htmlspecialchars((string)($r['time']??'09:00')) ?>" required></label>
 <label><span>Type de contenu</span><select name="cadence[<?= $i ?>][type]" required><option value="Visuel">Visuel</option><option value="Video" <?= ($r['type']??'')==='Video'?'selected':'' ?>>Vidéo</option></select></label>
 <label><span>Récurrence</span><select name="cadence[<?= $i ?>][frequency]" required><option value="weekly" <?= $frequency==='weekly'?'selected':'' ?>>Hebdomadaire</option><option value="biweekly" <?= $frequency==='biweekly'?'selected':'' ?>>Toutes les 2 semaines</option><option value="monthly" <?= $frequency==='monthly'?'selected':'' ?>>Mensuelle</option></select></label>
 <label class="cadence-topic"><span>Thématique <small>facultative</small></span><input name="cadence[<?= $i ?>][label]" value="<?= htmlspecialchars((string)($r['label']??'')) ?>" placeholder="Ex. Conseil, coulisses…" maxlength="160"></label>
 <label class="cadence-format"><span>Format <small>facultatif</small></span><input name="cadence[<?= $i ?>][format]" value="<?= htmlspecialchars((string)($r['format']??'')) ?>" placeholder="Carrousel, reel, démo…" maxlength="80"></label>
 <button type="button" class="cadence-remove" data-remove-cadence title="Supprimer ce créneau" aria-label="Supprimer ce créneau"><svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3m3 0-1 13H7L6 7m4 4v5m4-5v5"/></svg></button>
</article><?php };
?>
<details class="panel field-wide cadence-builder" <?= $cadenceRows?'open':'' ?>>
 <summary><span><strong>Rythme de publication</strong><small>Optionnel · <?= count($cadenceRows) ?> créneau(x)</small></span></summary>
 <div class="cadence-builder-body">
  <div class="cadence-intro"><div><strong>Planning récurrent prioritaire</strong><p>Ces créneaux remplacent la répartition du forfait. Les plafonds mensuels du projet restent la limite maximale.</p></div><button type="button" class="button secondary" data-add-cadence>+ Ajouter un créneau</button></div>
  <input type="hidden" name="cadence_present" value="1">
  <?php if(!empty($record['id'])): ?><div class="cadence-revision"><label><span>Appliquer à partir de</span><input type="month" name="cadence_effective_month" min="<?= date('Y-m',strtotime('first day of next month')) ?>" value="<?= htmlspecialchars((string)($_POST['cadence_effective_month']??date('Y-m',strtotime('first day of next month')))) ?>"></label><label class="cadence-confirm"><input type="checkbox" name="cadence_confirm_future" value="1"><span>Confirmer la modification des mois futurs. Les contenus déjà travaillés restent conservés.</span></label></div><?php endif ?>
  <div class="cadence-rows" data-cadence-rows><?php foreach($cadenceRows as$i=>$row)$renderCadenceRow($row,$i); ?></div>
  <div class="cadence-empty" data-cadence-empty <?= $cadenceRows?'hidden':'' ?>><strong>Aucun rythme personnalisé</strong><span>Les quotas de l’abonnement seront utilisés.</span></div>
 </div>
</details>
<template data-cadence-template><?php $renderCadenceRow([],0); ?></template>
<script src="<?= htmlspecialchars(app_url('/public/assets/editorial-cadence.js?v=1')) ?>"></script>
