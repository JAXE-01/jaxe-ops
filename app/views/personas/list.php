<h1>Liste des personas</h1>
<a href="/persona/create">Ajouter un persona</a>
<div class="table-wrap compact-table" style="margin-top: 12px;">
    <table>
        <tr>
            <th>ID</th><th>Client</th><th>Nom</th><th>Âge</th><th>Profession</th><th>Revenu</th><th>Localisation</th><th>Objectif</th><th>Actions</th>
        </tr>
        <?php foreach ($personas as $persona): ?>
        <tr>
            <td><?= htmlspecialchars($persona['id']) ?></td>
            <td><?= htmlspecialchars($persona['client_id']) ?></td>
            <td><?= htmlspecialchars($persona['nom_persona']) ?></td>
            <td><?= htmlspecialchars($persona['age']) ?></td>
            <td><?= htmlspecialchars($persona['profession']) ?></td>
            <td><?= htmlspecialchars($persona['revenu']) ?></td>
            <td><?= htmlspecialchars($persona['localisation']) ?></td>
            <td><?= htmlspecialchars($persona['objectif']) ?></td>
            <td>
                <a href="/persona/edit/<?= $persona['id'] ?>">Éditer</a> |
                <a href="/persona/delete/<?= $persona['id'] ?>" onclick="return confirm('Supprimer ce persona ?');">Supprimer</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
