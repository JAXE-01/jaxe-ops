<?php
$items = is_array($items ?? null) ? $items : [];
?>
<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Corbeille des uploads</h2>
            <p class="panel-subtitle">Les fichiers supprimes sont deplaces ici. La suppression depuis cette page est definitive.</p>
        </div>
    </div>

    <div class="table-wrap compact-table">
        <table>
            <thead>
            <tr>
                <th>Date suppression</th>
                <th>Nom</th>
                <th>Chemin d origine</th>
                <th>Taille</th>
                <th>Module</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($item['deleted_at'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($item['original_name'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($item['original_path'] ?? '')) ?></td>
                    <td><?= number_format((int) ($item['size_bytes'] ?? 0), 0, ',', ' ') ?> o</td>
                    <td><?= htmlspecialchars((string) ($item['module_key'] ?? '')) ?></td>
                    <td>
                        <form method="post" action="<?= htmlspecialchars(route_url('/trash/purge/' . (int) ($item['id'] ?? 0))) ?>" onsubmit="return confirm('Supprimer definitivement ce fichier ?');">
                            <button class="button ghost" type="submit">Suppression definitive</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="6">Corbeille vide.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
