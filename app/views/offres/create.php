<h1>Ajouter une offre</h1>
<form method="post">
    <label>Client ID: <input type="number" name="client_id" required></label><br>
    <label>Produit/Service: <input type="text" name="produit_service" required></label><br>
    <label>Description: <textarea name="description"></textarea></label><br>
    <label>Prix: <input type="number" step="0.01" name="prix"></label><br>
    <label>Packages (JSON): <input type="text" name="packages"></label><br>
    <label>Avantage offre: <textarea name="avantage_offre"></textarea></label><br>
    <label>USP: <textarea name="usp"></textarea></label><br>
    <label>Positionnement: <input type="text" name="positionnement"></label><br>
    <button type="submit">Créer</button>
</form>
<a href="/offre">Retour à la liste</a>
