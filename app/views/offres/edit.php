<h1>Éditer l'offre</h1>
<form method="post">
    <label>Client ID: <input type="number" name="client_id" value="<?= htmlspecialchars($offre['client_id']) ?>" required></label><br>
    <label>Produit/Service: <input type="text" name="produit_service" value="<?= htmlspecialchars($offre['produit_service']) ?>" required></label><br>
    <label>Description: <textarea name="description"><?= htmlspecialchars($offre['description']) ?></textarea></label><br>
    <label>Prix: <input type="number" step="0.01" name="prix" value="<?= htmlspecialchars($offre['prix']) ?>"></label><br>
    <label>Packages (JSON): <input type="text" name="packages" value="<?= htmlspecialchars($offre['packages']) ?>"></label><br>
    <label>Avantage offre: <textarea name="avantage_offre"><?= htmlspecialchars($offre['avantage_offre']) ?></textarea></label><br>
    <label>USP: <textarea name="usp"><?= htmlspecialchars($offre['usp']) ?></textarea></label><br>
    <label>Positionnement: <input type="text" name="positionnement" value="<?= htmlspecialchars($offre['positionnement']) ?>"></label><br>
    <button type="submit">Enregistrer</button>
</form>
<a href="/offre">Retour à la liste</a>
