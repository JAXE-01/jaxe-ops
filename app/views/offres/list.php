<h1>Liste des offres</h1>
<a href="/offre/create">Ajouter une offre</a>
<div class="table-wrap compact-table" style="margin-top: 12px;">
    <table>
        <tr>
            <th>ID</th><th>Client</th><th>Produit/Service</th><th>Description</th><th>Prix</th><th>Packages</th><th>Avantage</th><th>USP</th><th>Positionnement</th><th>Actions</th>
        </tr>
        <?php foreach ($offres as $offre): ?>
        <tr>
            <td><?= htmlspecialchars($offre['id']) ?></td>
            <td><?= htmlspecialchars($offre['client_id']) ?></td>
            <td><?= htmlspecialchars($offre['produit_service']) ?></td>
            <td><?= htmlspecialchars($offre['description']) ?></td>
            <td><?= htmlspecialchars($offre['prix']) ?></td>
            <td><?= htmlspecialchars($offre['packages']) ?></td>
            <td><?= htmlspecialchars($offre['avantage_offre']) ?></td>
            <td><?= htmlspecialchars($offre['usp']) ?></td>
            <td><?= htmlspecialchars($offre['positionnement']) ?></td>
            <td>
                <a href="/offre/edit/<?= $offre['id'] ?>">Éditer</a> |
                <a href="/offre/delete/<?= $offre['id'] ?>" onclick="return confirm('Supprimer cette offre ?');">Supprimer</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
