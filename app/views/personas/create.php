<h1>Ajouter un persona</h1>
<form method="post">
    <label>Client ID: <input type="number" name="client_id" required></label><br>
    <label>Nom: <input type="text" name="nom_persona" required></label><br>
    <label>Âge: <input type="number" name="age"></label><br>
    <label>Profession: <input type="text" name="profession"></label><br>
    <label>Revenu: <input type="number" step="0.01" name="revenu"></label><br>
    <label>Localisation: <input type="text" name="localisation"></label><br>
    <label>Objectif: <textarea name="objectif"></textarea></label><br>
    <label>Problème: <textarea name="probleme"></textarea></label><br>
    <label>Craintes: <textarea name="craintes"></textarea></label><br>
    <label>Désirs: <textarea name="desirs"></textarea></label><br>
    <label>Déclencheur achat: <textarea name="declencheur_achat"></textarea></label><br>
    <label>Freins: <textarea name="freins"></textarea></label><br>
    <label>Valeur perçue: <textarea name="valeur_percue"></textarea></label><br>
    <label>Garanties: <textarea name="garanties"></textarea></label><br>
    <label>Canaux: <input type="text" name="canaux"></label><br>
    <label>Horaires: <input type="text" name="horaires"></label><br>
    <label>Priorité:
        <select name="priorite">
            <option value="Haute">Haute</option>
            <option value="Moyenne">Moyenne</option>
            <option value="Basse">Basse</option>
        </select>
    </label><br>
    <button type="submit">Créer</button>
</form>
<a href="/persona">Retour à la liste</a>
