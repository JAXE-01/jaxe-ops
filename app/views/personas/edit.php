<h1>Éditer le persona</h1>
<form method="post">
    <label>Client ID: <input type="number" name="client_id" value="<?= htmlspecialchars($persona['client_id']) ?>" required></label><br>
    <label>Nom: <input type="text" name="nom_persona" value="<?= htmlspecialchars($persona['nom_persona']) ?>" required></label><br>
    <label>Âge: <input type="number" name="age" value="<?= htmlspecialchars($persona['age']) ?>"></label><br>
    <label>Profession: <input type="text" name="profession" value="<?= htmlspecialchars($persona['profession']) ?>"></label><br>
    <label>Revenu: <input type="number" step="0.01" name="revenu" value="<?= htmlspecialchars($persona['revenu']) ?>"></label><br>
    <label>Localisation: <input type="text" name="localisation" value="<?= htmlspecialchars($persona['localisation']) ?>"></label><br>
    <label>Objectif: <textarea name="objectif"><?= htmlspecialchars($persona['objectif']) ?></textarea></label><br>
    <label>Problème: <textarea name="probleme"><?= htmlspecialchars($persona['probleme']) ?></textarea></label><br>
    <label>Craintes: <textarea name="craintes"><?= htmlspecialchars($persona['craintes']) ?></textarea></label><br>
    <label>Désirs: <textarea name="desirs"><?= htmlspecialchars($persona['desirs']) ?></textarea></label><br>
    <label>Déclencheur achat: <textarea name="declencheur_achat"><?= htmlspecialchars($persona['declencheur_achat']) ?></textarea></label><br>
    <label>Freins: <textarea name="freins"><?= htmlspecialchars($persona['freins']) ?></textarea></label><br>
    <label>Valeur perçue: <textarea name="valeur_percue"><?= htmlspecialchars($persona['valeur_percue']) ?></textarea></label><br>
    <label>Garanties: <textarea name="garanties"><?= htmlspecialchars($persona['garanties']) ?></textarea></label><br>
    <label>Canaux: <input type="text" name="canaux" value="<?= htmlspecialchars($persona['canaux']) ?>"></label><br>
    <label>Horaires: <input type="text" name="horaires" value="<?= htmlspecialchars($persona['horaires']) ?>"></label><br>
    <label>Priorité:
        <select name="priorite">
            <option value="Haute" <?= $persona['priorite']==='Haute'?'selected':'' ?>>Haute</option>
            <option value="Moyenne" <?= $persona['priorite']==='Moyenne'?'selected':'' ?>>Moyenne</option>
            <option value="Basse" <?= $persona['priorite']==='Basse'?'selected':'' ?>>Basse</option>
        </select>
    </label><br>
    <button type="submit">Enregistrer</button>
</form>
<a href="/persona">Retour à la liste</a>
