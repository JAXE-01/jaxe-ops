<?php
$selectedNetwork=(string)($_POST['reseau_cible']??$deliverable['reseau_cible']??$deliverable['canal']??$deliverable['canal_principal']??'');
$networkOptions=array_values(array_unique(array_filter(array_merge(['Facebook','Instagram','LinkedIn','TikTok','YouTube','X','Threads','WhatsApp'],(array)($tpackRefs['platforms']??[]),[$selectedNetwork]))));
?>
<select name="reseau_cible" <?= !$canEdit?'disabled':'' ?>>
<option value="">Sélectionner un réseau</option>
<?php foreach($networkOptions as $network): ?><option value="<?= htmlspecialchars($network) ?>" <?= $network===$selectedNetwork?'selected':'' ?>><?= htmlspecialchars($network) ?></option><?php endforeach ?>
</select>
