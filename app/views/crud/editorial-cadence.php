<?php $cadenceRows=$_POST['cadence']??CadenceRevision::latest((string)($record['publication_rules']??'')); ?>
<details class="panel field-wide"><summary><strong>Rythme de publication hebdomadaire (optionnel)</strong></summary>
<p>Une ligne par rendez-vous. Sans règle, les quotas de l’abonnement sont utilisés. Avec des règles, le nombre mensuel dépend des dates réelles.</p>
<p class="mini-text">L’alternance commence la semaine de début du projet. Pour alterner deux vidéos : vendredi, toutes les 2 semaines, phase 1 puis phase 2. Les mois passés sont conservés. Les contenus personnalisés et les rendez-vous excédentaires restent en place : aucune suppression automatique.</p>
<input type="hidden" name="cadence_present" value="1">
<?php if(!empty($record['id'])): ?>
<label>Appliquer à partir de<input type="month" name="cadence_effective_month" min="<?= date('Y-m',strtotime('first day of next month')) ?>" value="<?= htmlspecialchars((string)($_POST['cadence_effective_month']??date('Y-m',strtotime('first day of next month')))) ?>"></label>
<label><input type="checkbox" name="cadence_confirm_future" value="1"> Confirmer la révision future. Les contenus déjà travaillés et les créneaux supplémentaires seront conservés comme exceptions.</label>
<?php $history=CadenceRevision::decode((string)($record['publication_rules']??''));foreach($history['revisions'] as $effective=>$revision): ?>
<p class="mini-text">Révision <?= htmlspecialchars($effective) ?> · <?= (int)($revision['summary']['moved']??0) ?> créneaux adaptés · <?= (int)($revision['summary']['preserved']??0)+(int)($revision['summary']['extra']??0) ?> conservés.</p>
<?php endforeach;endif ?>
<?php for($i=0;$i<max(5,count($cadenceRows));$i++): $r=$cadenceRows[$i]??[]; ?>
<fieldset style="display:flex;flex-wrap:wrap;gap:12px;margin:12px 0;padding:12px;border:1px solid #dce4ef;border-radius:12px">
<legend>Rendez-vous <?= $i+1 ?></legend>
<label>Intention<input name="cadence[<?= $i ?>][label]" value="<?= htmlspecialchars($r['label']??'') ?>" placeholder="Ex. Conseil éducatif" maxlength="160"></label>
<label>Jour<select name="cadence[<?= $i ?>][day]"><?php foreach([1=>'Lundi',2=>'Mardi',3=>'Mercredi',4=>'Jeudi',5=>'Vendredi',6=>'Samedi',7=>'Dimanche']as$d=>$label): ?><option value="<?= $d ?>" <?= (int)($r['day']??1)===$d?'selected':'' ?>><?= $label ?></option><?php endforeach ?></select></label>
<label>Type<select name="cadence[<?= $i ?>][type]"><option value="Visuel">Visuel</option><option value="Video" <?= ($r['type']??'')==='Video'?'selected':'' ?>>Vidéo</option></select></label>
<label>Format<input name="cadence[<?= $i ?>][format]" value="<?= htmlspecialchars($r['format']??'') ?>" placeholder="Carrousel, démo…"></label>
<label>Fréquence<select name="cadence[<?= $i ?>][every]"><option value="1">Chaque semaine</option><option value="2" <?= (int)($r['every']??1)===2?'selected':'' ?>>Toutes les 2 semaines</option></select></label>
<label>Phase<select name="cadence[<?= $i ?>][phase]"><option value="0">Semaine 1</option><option value="1" <?= (int)($r['phase']??0)===1?'selected':'' ?>>Semaine 2 (alternance)</option></select></label>
</fieldset><?php endfor ?></details>
