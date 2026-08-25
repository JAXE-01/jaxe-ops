<?php $legal = is_array($legal ?? null) ? $legal : []; ?>
<link rel="stylesheet" href="<?= htmlspecialchars(app_url('/public/assets/legal-pages.css')) ?>">
<div class="public-page legal-page">
    <header class="legal-hero">
        <a class="legal-back" href="<?= htmlspecialchars(route_url('/')) ?>">← Retour à Strax</a>
        <span class="eyebrow"><?= htmlspecialchars((string) ($legal['eyebrow'] ?? 'Informations')) ?></span>
        <h1><?= htmlspecialchars((string) ($legal['title'] ?? 'Informations légales')) ?></h1>
        <p><?= htmlspecialchars((string) ($legal['intro'] ?? '')) ?></p>
        <small>Dernière mise à jour : <?= htmlspecialchars((string) ($legal['updated'] ?? '')) ?></small>
    </header>
    <main class="legal-content">
        <?php foreach (($legal['sections'] ?? []) as $index => $section): ?>
            <section>
                <span><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                <div><h2><?= htmlspecialchars((string) ($section[0] ?? '')) ?></h2><p><?= htmlspecialchars((string) ($section[1] ?? '')) ?></p></div>
            </section>
        <?php endforeach; ?>
        <nav class="legal-links" aria-label="Documents juridiques">
            <a href="<?= htmlspecialchars(route_url('/public/privacy')) ?>">Confidentialité</a>
            <a href="<?= htmlspecialchars(route_url('/public/terms')) ?>">Conditions d’utilisation</a>
            <a href="<?= htmlspecialchars(route_url('/public/data-deletion')) ?>">Suppression des données</a>
        </nav>
    </main>
</div>

