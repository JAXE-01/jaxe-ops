<form method="get" class="matrix-quick-select" data-matrix-quick-select data-workspace-accessory="month">
 <label class="field"><span>Matrice de composition du client</span><select name="composition_matrix_id" onchange="this.form.requestSubmit()"><option value="0">Choisir une matrice</option>
 <?php foreach($compositionContext['matrices'] as $option): ?><option value="<?= (int)$option['id'] ?>" <?= (int)($compositionContext['selected']['id']??0)===(int)$option['id']?'selected':'' ?>><?= htmlspecialchars($option['name']) ?></option><?php endforeach ?>
 </select></label>
 <span class="context-info" tabindex="0" title="Les références viennent uniquement des matrices de ce client. Les valeurs déjà enregistrées restent conservées." aria-label="Les références viennent uniquement des matrices de ce client. Les valeurs déjà enregistrées restent conservées.">ⓘ</span>
</form>
