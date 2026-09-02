<?php
$developerResources = [
    'TikTok' => [
        'Applications' => 'https://developers.tiktok.com/apps/',
        'Configuration' => 'https://developers.tiktok.com/doc/getting-started-create-an-app/',
        'API publicitaire' => 'https://business-api.tiktok.com/portal',
    ],
    'LinkedIn' => [
        'Applications' => 'https://www.linkedin.com/developers/apps',
        'Produits et autorisations' => 'https://learn.microsoft.com/en-us/linkedin/marketing/increasing-access',
    ],
    'YouTube' => [
        'Console Google Cloud' => 'https://console.cloud.google.com/',
        'Identifiants OAuth' => 'https://console.cloud.google.com/apis/credentials',
        'Statistiques et autorisations' => 'https://developers.google.com/youtube/reporting/guides/registering_an_application',
    ],
    'Meta / Instagram' => [
        'Applications' => 'https://developers.facebook.com/apps/',
        'Documentation Instagram' => 'https://developers.facebook.com/docs/instagram-platform/',
    ],
];
?>
<footer class="developer-footer" aria-label="Ressources et informations du site">
    <details>
        <summary>Liens utiles · Connexions des réseaux</summary>
        <nav class="developer-footer-grid" aria-label="Portails développeur officiels">
            <?php foreach ($developerResources as $network => $links): ?>
                <section>
                    <h2><?= htmlspecialchars($network, ENT_QUOTES, 'UTF-8') ?></h2>
                    <?php foreach ($links as $label => $url): ?>
                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            <span aria-hidden="true">↗</span><span class="developer-footer-sr"> (nouvel onglet)</span>
                        </a>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        </nav>
        <p>Portails officiels pour préparer vos accès. Leur configuration ne connecte pas automatiquement le réseau à Strax. Ne partagez jamais vos clés secrètes.</p>
    </details>
    <nav class="developer-footer-legal" aria-label="Informations légales">
        <span>Strax</span>
        <a href="<?= htmlspecialchars(route_url('/public/privacy'), ENT_QUOTES, 'UTF-8') ?>">Confidentialité</a>
        <a href="<?= htmlspecialchars(route_url('/public/terms'), ENT_QUOTES, 'UTF-8') ?>">Conditions</a>
        <a href="<?= htmlspecialchars(route_url('/public/data-deletion'), ENT_QUOTES, 'UTF-8') ?>">Suppression des données</a>
    </nav>
</footer>
