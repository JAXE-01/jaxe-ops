<form method="get" class="panel" onsubmit="return confirm('Les modifications doivent être sauvegardées avant de changer de matrice. Continuer ?')">
 <label class="field"><span>Matrice de composition du client</span><select name="composition_matrix_id"><option value="0">Choisir une matrice</option>
 <?php foreach($compositionContext['matrices'] as $option): ?><option value="<?= (int)$option['id'] ?>" <?= (int)($compositionContext['selected']['id']??0)===(int)$option['id']?'selected':'' ?>><?= htmlspecialchars($option['name']) ?></option><?php endforeach ?>
 </select></label><button class="button secondary">Utiliser cette matrice</button>
 <p class="mini-text">Les références viennent uniquement des matrices de ce client. Les valeurs déjà enregistrées restent conservées.</p>
</form>
