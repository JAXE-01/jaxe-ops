<?php $compatibility=MatrixCompatibility::rules($matrix); ?>
<details class="panel mx-settings" <?= $compatibility===null?'open':'' ?>>
<summary><strong>Compatibilités produits / cibles</strong><span><?= $compatibility===null?'À configurer avant génération':'Modifier les associations' ?></span></summary>
<p>Cochez les cibles pertinentes pour chaque produit ou service. Une ligne vide exclut ce produit de la génération automatique. Ces règles ne jugent pas la pertinence des besoins ou des formats.</p>
<form method="post"><?php $hidden($matrix['id'],$clientId,$projectId,$month) ?><input type="hidden" name="action" value="save_compatibility">
<?php foreach($matrix['product_list']as$pi=>$product):?><fieldset style="margin:12px 0;padding:14px;border:1px solid #dde5ef;border-radius:12px"><legend><?= $e($product) ?></legend>
<?php foreach($matrix['target_list']as$ti=>$target):?><label style="display:inline-flex;gap:6px;padding:8px;align-items:center"><input style="width:auto" type="checkbox" name="compatibility[<?= $pi ?>][]" value="<?= $ti ?>" <?= in_array($target,(array)($compatibility[$product]??[]),true)?'checked':'' ?>><?= $e($target) ?></label><?php endforeach ?></fieldset><?php endforeach ?>
<button class="button" type="submit">Enregistrer les compatibilités</button></form></details>
