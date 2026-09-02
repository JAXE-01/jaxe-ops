<?php
$flash = $this->getFlash();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$currentUser = $this->currentUser();
$organizationContext = $currentUser ? OrganizationContext::forUser($currentUser) : null;
$isPlatformAdmin=$currentUser&&OrganizationContext::isPlatformAdmin($currentUser);$workspaceMode=$isPlatformAdmin?(string)($_SESSION['workspace_mode']??'agency'):'organization';
$settingsModel = new SettingsModel();
$brandingConfig = $settingsModel->getBrandingConfig();
$appName = trim((string) ($brandingConfig['app_name'] ?? 'Strax'));
if ($appName === '') {
    $appName = 'Strax';
}
$brandCaption = trim((string) ($brandingConfig['brand_caption'] ?? 'Operations editoriales et pilotage client'));
$brandLogoUrl = trim((string) ($brandingConfig['logo_url'] ?? ''));
if ($brandLogoUrl === '') {
    $brandLogoUrl = app_url('/public/assets/favicon.svg');
}

$roleProfile = 'default';
if ($currentUser) {
    $roles = UserRoles::extractRoles($currentUser);
    if (in_array('Clientele', $roles, true)) {
        $roleProfile = 'clientele';
    } elseif (in_array('CC', $roles, true)) {
        $roleProfile = 'cc';
    } else {
        $prodRoles = ['CM', 'Createur', 'Cadreur', 'Designer', 'Videaste'];
        foreach ($prodRoles as $prodRole) {
            if (in_array($prodRole, $roles, true)) {
                $roleProfile = 'prod';
                break;
            }
        }
    }
}

$mainNavItems = [
    [
        'label' => 'Dashboard',
        'href' => route_url('/'),
        'match' => [route_url('/'), route_url('')],
        'permission' => 'dashboard.view',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h7v7H4V4Zm9 0h7v4h-7V4ZM4 13h4v7H4v-7Zm6 0h10v7H10v-7Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>'
    ],
    [
        'label' => 'Démarrage',
        'href' => route_url('/onboarding'),
        'match' => [route_url('/onboarding')],
        'permission' => 'dashboard.view',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9M12 7v5l3 2M17 3h4v4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    ],
    [
        'label' => 'Statistiques & rapports',
        'href' => route_url('/reporting-metric'),
        'match' => [route_url('/reporting-metric')],
        'permission' => 'reporting.view',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V10m6 10V4m6 16v-7m4 7H2" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    ],
    [
        'label' => 'Calendrier global',
        'href' => route_url('/calendrier-global'),
        'match' => [route_url('/calendrier-global')],
        'permission' => 'calendar.view',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2v3M17 2v3M4 8h16M6 5h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm2 7h3v3H8v-3Zm5 0h3v3h-3v-3Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    ],
    [
        'label' => 'Pilotage',
        'href' => route_url('/calendrier'),
        'match' => [route_url('/calendrier')],
        'permission' => 'calendar.view',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm0 0v8m0-8 6-2m-6 2-6-2m6 2 5 5m-5-5-5 5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    ],
    [
        'label' => 'Clients',
        'href' => route_url('/client'),
        'match' => [route_url('/client')],
        'permission' => 'clients.view',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 19v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1M9 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8 3a3 3 0 1 1 0-6m4 12v-1a3 3 0 0 0-2-2.83" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    ],
    [
        'label' => 'Projets',
        'href' => route_url('/projet'),
        'match' => [route_url('/projet')],
        'permission' => 'projects.view',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7.5 12 3l9 4.5-9 4.5-9-4.5Zm0 4.5 9 4.5 9-4.5M3 16.5 12 21l9-4.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    ],
    [
        'label' => 'Matrice de creation',
        'href' => route_url('/matrix'),
        'match' => [route_url('/matrix')],
        'permission' => 'content.view',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 0h6v6h-6v-6Z" fill="none" stroke="currentColor" stroke-width="1.7"/></svg>'
    ],
    [
        'label' => 'Publication sociale',
        'href' => route_url('/social-publishing'),
        'match' => [route_url('/social-publishing')],
        'permission' => 'publishing.view',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 12 16-8-5 16-3-6-8-2Zm8 2 8-10" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    ],
    [
        'label' => 'Messages & commentaires',
        'href' => route_url('/social-inbox'),
        'match' => [route_url('/social-inbox')],
        'permission' => 'publishing.view',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H9l-5 4V5Zm4 4h8M8 12h5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    ],
    [
        'label' => 'Documentation',
        'href' => route_url('/documentation'),
        'match' => [route_url('/documentation')],
        'permission' => 'calendar.view',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5a2 2 0 0 1 2-2h11a3 3 0 0 1 3 3v13a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2V5Zm3 1h8M7 10h10M7 14h7" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    ],
    [
        'label' => 'Realisations',
        'href' => route_url('/realisation'),
        'match' => [route_url('/realisation')],
        'permission' => 'calendar.view',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6Zm2 10 3.5-4 2.5 3 2-2.5L18 16M9 9.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    ],
    [
        'label' => 'Export documents',
        'href' => route_url('/export-document'),
        'match' => [route_url('/export-document')],
        'permission' => 'calendar.view',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v11m0 0 4-4m-4 4-4-4M5 21h14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    ]
,
    [
        'label' => 'Administration SaaS',
        'href' => route_url('/platform'),
        'match' => [route_url('/platform')],
        'permission' => 'settings.manage',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 21V5l8-3 8 3v16M8 8h2m4 0h2M8 12h2m4 0h2M8 16h2m4 0h2M2 21h20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    ],
    [
        'label' => 'Acces agences',
        'href' => route_url('/agency-access'),
        'match' => [route_url('/agency-access')],
        'permission' => 'dashboard.view',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.07.07l2-2a5 5 0 0 0-7.07-7.07l-1.15 1.15M14 11a5 5 0 0 0-7.07-.07l-2 2A5 5 0 0 0 12 20l1.15-1.15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>'
    ],
    [
        'label' => 'Mon equipe',
        'href' => route_url('/team'),
        'match' => [route_url('/team')],
        'permission' => 'users.view',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm9-1v6m3-3h-6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>'
    ]];

$profileHref = route_url('/account');
$profileMatch = [route_url('/account')];
$settingsHref = route_url('/settings');
$settingsMatch = [route_url('/settings'), route_url('/abonnement')];

$settingsIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.3 4.9a1 1 0 0 1 .95-.7h1.5a1 1 0 0 1 .95.7l.42 1.34a7.78 7.78 0 0 1 1.33.55l1.28-.63a1 1 0 0 1 1.16.2l1.06 1.06a1 1 0 0 1 .2 1.16l-.63 1.28c.22.42.41.86.55 1.33l1.34.42a1 1 0 0 1 .7.95v1.5a1 1 0 0 1-.7.95l-1.34.42a7.78 7.78 0 0 1-.55 1.33l.63 1.28a1 1 0 0 1-.2 1.16l-1.06 1.06a1 1 0 0 1-1.16.2l-1.28-.63a7.78 7.78 0 0 1-1.33.55l-.42 1.34a1 1 0 0 1-.95.7h-1.5a1 1 0 0 1-.95-.7l-.42-1.34a7.78 7.78 0 0 1-1.33-.55l-1.28.63a1 1 0 0 1-1.16-.2L4.9 18.6a1 1 0 0 1-.2-1.16l.63-1.28a7.78 7.78 0 0 1-.55-1.33l-1.34-.42a1 1 0 0 1-.7-.95v-1.5a1 1 0 0 1 .7-.95l1.34-.42a7.78 7.78 0 0 1 .55-1.33L4.7 8.03a1 1 0 0 1 .2-1.16L5.96 5.8a1 1 0 0 1 1.16-.2l1.28.63c.42-.22.86-.41 1.33-.55l.42-1.34ZM12 15.5A3.5 3.5 0 1 0 12 8.5a3.5 3.5 0 0 0 0 7Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$profileIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm-8 9a8 8 0 0 1 16 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$logoutIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$mobileMoreIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';

if (!function_exists('is_nav_active')) {
    function normalize_nav_path($path) {
        $path = parse_url((string) $path, PHP_URL_PATH);
        $path = '/' . ltrim((string) $path, '/');
        if (strlen($path) > 1) {
            $path = rtrim($path, '/');
        }
        return $path === '' ? '/' : $path;
    }

    function is_nav_active($currentPath, array $matches) {
        $currentPath = normalize_nav_path($currentPath);
        $rootPath = normalize_nav_path(route_url('/'));
        foreach ($matches as $match) {
            $match = normalize_nav_path($match);
            if ($match === '/' || $match === $rootPath) {
                if ($currentPath === '/') {
                    return true;
                }
                if ($currentPath === $rootPath) {
                    return true;
                }
                continue;
            }
            if ($currentPath === $match || strpos($currentPath, $match . '/') === 0) {
                return true;
            }
        }
        return false;
    }
}

$allowedMainNavItems = [];
if ($currentUser) {
    foreach ($mainNavItems as $item) {
        if (!$this->can($item['permission'] ?? null)) {
            continue;
        }
        if (($item['label'] ?? '') === 'Administration SaaS' && (!$isPlatformAdmin || $workspaceMode !== 'platform')) { continue; }
        if (($item['label'] ?? '') === 'Acces agences') { $membership=(string)($organizationContext['membership_role']??''); if(!$isPlatformAdmin&&!in_array($membership,['Owner','Admin'],true)){continue;} }
        if ($workspaceMode === 'platform' && ($item['label'] ?? '') !== 'Administration SaaS') { continue; }
        $allowedMainNavItems[] = $item;
    }
}

$mobilePrimaryNavItems = array_slice($allowedMainNavItems, 0, 4);
$mobileOverflowNavItems = array_slice($allowedMainNavItems, 4);

$mobileUtilityItems = [];
if ($currentUser) {
    if ($this->can('settings.view')) {
        $mobileUtilityItems[] = [
            'label' => 'Parametres',
            'href' => $settingsHref,
            'active' => is_nav_active($currentPath, $settingsMatch),
            'icon' => $settingsIcon,
        ];
    }
    $mobileUtilityItems[] = [
        'label' => 'Profil',
        'href' => $profileHref,
        'active' => is_nav_active($currentPath, $profileMatch),
        'icon' => $profileIcon,
    ];
    $mobileUtilityItems[] = [
        'label' => 'Sortie',
        'href' => route_url('/logout'),
        'active' => false,
        'icon' => $logoutIcon,
    ];
}

$mobileHasOverflow = !empty($mobileOverflowNavItems) || !empty($mobileUtilityItems);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#14283f">
    <link rel="manifest" href="<?= htmlspecialchars(app_url('/manifest.webmanifest')) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars(app_url('/public/assets/brand/app-192.png')) ?>">
    <title><?= htmlspecialchars($pageTitle ?? 'Strax') ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars(app_url('/public/assets/favicon.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('/public/assets/style.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('/public/assets/sidebar.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('/public/assets/public-site.css?v=20260826-1')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('/public/assets/app-experience.css?v=2')) ?>">
</head>
<body class="<?= $currentUser ? 'authenticated-user' : 'public-visitor' ?>" data-role-profile="<?= htmlspecialchars($roleProfile) ?>">
<div class="global-loader" id="globalLoader" aria-hidden="true" role="status">
    <div class="global-loader-inner">
        <span class="global-loader-spinner"></span>
        <span>Chargement en cours...</span>
    </div>
</div>
<div class="app-shell">
    <header class="shell-header shell-header-unified">
        <a class="brand-block brand-block-inline" href="<?= htmlspecialchars(route_url('/')) ?>">
            <span class="brand-logo-wrap">
                <img src="<?= htmlspecialchars($brandLogoUrl) ?>" alt="Logo" class="brand-logo">
            </span>
            <span>
                <span class="brand"><?= htmlspecialchars($appName) ?></span>
                <span class="brand-caption"><?= htmlspecialchars($brandCaption) ?></span>
            </span>
        </a>

        <?php if ($currentUser): ?>
            <div class="sidebar-context">
                <small><?= $isPlatformAdmin ? ($workspaceMode==='platform'?'Administration SaaS':'Espace agence') : 'Espace entreprise' ?></small>
                <strong><?= htmlspecialchars($organizationContext['name'] ?? 'Jaxe Communication') ?></strong>
                <span><?= htmlspecialchars($organizationContext['account_type'] ?? 'Agency') ?> · <?= htmlspecialchars($organizationContext['membership_role'] ?? '') ?></span>
            </div>
                        <?php if ($isPlatformAdmin): ?><div class="workspace-switch" aria-label="Changer d espace"><a class="<?= $workspaceMode==='platform'?'active':'' ?>" href="<?= htmlspecialchars(route_url('/workspace-mode/mode/platform')) ?>">SaaS</a><a class="<?= $workspaceMode==='agency'?'active':'' ?>" href="<?= htmlspecialchars(route_url('/workspace-mode/mode/agency')) ?>">Agence</a></div><?php endif; ?>
            <nav class="top-nav top-nav-unified" aria-label="Navigation principale">
                <?php $lastNavGroup = ''; ?>
                <?php foreach ($allowedMainNavItems as $item): ?>
                    <?php
                    $navLabel = (string) ($item['label'] ?? '');
                    if (in_array($navLabel, ['Dashboard', 'Démarrage', 'Calendrier global', 'Pilotage', 'Clients', 'Projets'], true)) {
                        $navGroup = 'Espace de travail';
                    } elseif (in_array($navLabel, ['Matrice de creation', 'Publication sociale', 'Statistiques & rapports', 'Documentation', 'Realisations', 'Export documents'], true)) {
                        $navGroup = 'Contenus & livrables';
                    } else {
                        $navGroup = 'Administration';
                    }
                    ?>
                    <?php if ($navGroup !== $lastNavGroup): ?>
                        <span class="sidebar-nav-heading"><?= htmlspecialchars($navGroup) ?></span>
                        <?php $lastNavGroup = $navGroup; ?>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($item['href']) ?>" class="nav-pill nav-pill-icon <?= is_nav_active($currentPath, $item['match']) ? 'active' : '' ?>" data-nav-label="<?= htmlspecialchars($item['label']) ?>" title="<?= htmlspecialchars($item['label']) ?>" aria-label="<?= htmlspecialchars($item['label']) ?>">
                        <span class="nav-icon"><?= $item['icon'] ?? '' ?></span>
                        <span class="nav-label-text"><?= htmlspecialchars($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="nav-utility">
                <?php if ($this->can('settings.view')): ?>
                    <a class="nav-icon-button <?= is_nav_active($currentPath, $settingsMatch) ? 'active' : '' ?>" href="<?= htmlspecialchars($settingsHref) ?>" title="Parametres" aria-label="Parametres">
                        <?= $settingsIcon ?><span class="nav-utility-label">Paramètres</span>
                    </a>
                <?php endif; ?>
                <a class="nav-icon-button <?= is_nav_active($currentPath, $profileMatch) ? 'active' : '' ?>" href="<?= htmlspecialchars($profileHref) ?>" title="Profil" aria-label="Profil">
                    <?= $profileIcon ?><span class="nav-utility-label">Mon profil</span>
                </a>
                <form method="post" action="<?= htmlspecialchars(route_url('/logout')) ?>" class="logout-form">
                    <button type="submit" class="nav-icon-button" aria-label="Déconnexion"><?= $logoutIcon ?><span class="nav-utility-label">Déconnexion</span></button>
                </form>
            </div>
        <?php else: ?>
            <nav class="public-nav" aria-label="Navigation publique">
                <a href="<?= htmlspecialchars(route_url('/public/solutions')) ?>">Solutions</a>
                <a href="<?= htmlspecialchars(route_url('/pricing')) ?>">Tarifs</a>
                <a href="<?= htmlspecialchars(route_url('/login')) ?>">Connexion</a>
                <a class="public-nav-cta" href="<?= htmlspecialchars(route_url('/public/register')) ?>">Créer un compte</a>
            </nav>
        <?php endif; ?>
    </header>

    <main class="content"><?php require dirname(__DIR__).'/calendrier/month-context-bar.php'; ?>
        <header class="topbar">
            <div>
                <h1><?= htmlspecialchars($pageTitle ?? 'Strax') ?></h1>
            </div>
            <div class="page-context">
                <?php if ($currentUser): ?>
                    <span class="page-context-text">Tableau de bord collaboratif, optimisé desktop et mobile.</span>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($flash): ?>
            <div class="flash <?= htmlspecialchars($flash['type']) ?>" data-flash-message="<?= htmlspecialchars((string) ($flash['message'] ?? '')) ?>" data-flash-type="<?= htmlspecialchars((string) ($flash['type'] ?? 'info')) ?>"><?= htmlspecialchars($flash['message']) ?></div>
        <?php endif; ?>

        <div class="toast-stack" id="appToastStack" aria-live="polite" aria-atomic="true"></div>

        <?= $content ?>
        <?php require __DIR__ . '/developer-footer.php'; ?>
    </main>

    <?php if ($currentUser): ?>
        <nav class="mobile-bottom-nav" aria-label="Navigation mobile">
            <?php foreach ($mobilePrimaryNavItems as $item): ?>
                <a href="<?= htmlspecialchars($item['href']) ?>" class="mobile-nav-item <?= is_nav_active($currentPath, $item['match']) ? 'active' : '' ?>">
                    <span class="mobile-nav-icon"><?= $item['icon'] ?? '' ?></span>
                    <span class="mobile-nav-label"><?= htmlspecialchars($item['label']) ?></span>
                </a>
            <?php endforeach; ?>

            <?php if ($mobileHasOverflow): ?>
                <button type="button" class="mobile-nav-item mobile-nav-more-toggle" id="mobileNavMoreToggle" aria-expanded="false" aria-controls="mobileNavMoreSheet" aria-label="Plus d options">
                    <span class="mobile-nav-icon"><?= $mobileMoreIcon ?></span>
                    <span class="mobile-nav-label">Plus</span>
                </button>
            <?php endif; ?>
        </nav>

        <?php if ($mobileHasOverflow): ?>
            <div class="mobile-more-sheet" id="mobileNavMoreSheet" hidden>
                <div class="mobile-more-sheet-inner">
                    <?php foreach ($mobileOverflowNavItems as $item): ?>
                        <a href="<?= htmlspecialchars($item['href']) ?>" class="mobile-more-link <?= is_nav_active($currentPath, $item['match']) ? 'active' : '' ?>">
                            <span class="mobile-nav-icon"><?= $item['icon'] ?? '' ?></span>
                            <span><?= htmlspecialchars($item['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                    <?php foreach ($mobileUtilityItems as $item): ?>
                        <a href="<?= htmlspecialchars($item['href']) ?>" class="mobile-more-link <?= !empty($item['active']) ? 'active' : '' ?>">
                            <span class="mobile-nav-icon"><?= $item['icon'] ?? '' ?></span>
                            <span><?= htmlspecialchars($item['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script>
(function () {
    var loader = document.getElementById('globalLoader');
    if (!loader) {
        return;
    }

    var forms = document.querySelectorAll('form');
    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (event.defaultPrevented || form.hasAttribute('data-no-global-loader')) {
                return;
            }

            if (!form.checkValidity()) {
                event.preventDefault();
                if (window.AppUI && typeof window.AppUI.toast === 'function') {
                    window.AppUI.toast('error', 'Certains champs obligatoires sont incomplets.');
                }
                return;
            }

            var submitter = event.submitter;
            if (submitter && submitter.name) {
                var hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = submitter.name;
                hiddenInput.value = submitter.value;
                hiddenInput.setAttribute('data-submit-mirror', 'true');

                var existingMirror = form.querySelector('input[data-submit-mirror="true"][name="' + submitter.name.replace(/"/g, '\\"') + '"]');
                if (existingMirror) {
                    existingMirror.remove();
                }

                form.appendChild(hiddenInput);
            }

            var isDownloadForm = form.hasAttribute('data-download-form');
            var loadingTimer = setTimeout(function(){document.body.classList.add('is-loading');loader.setAttribute('aria-hidden','false');},450);
            window.addEventListener('pageshow',function(){clearTimeout(loadingTimer);document.body.classList.remove('is-loading');loader.setAttribute('aria-hidden','true');form.querySelectorAll('button,input[type="submit"]').forEach(function(control){control.disabled=false;});},{once:true});

            var controls = form.querySelectorAll('button, input[type="submit"]');
            controls.forEach(function (control) {
                if (!isDownloadForm) {
                    control.disabled = true;
                }
            });

            if (isDownloadForm) {
                // File downloads do not always navigate, so we release the global loader automatically.
                setTimeout(function () {
                    document.body.classList.remove('is-loading');
                    loader.setAttribute('aria-hidden', 'true');
                    controls.forEach(function (control) {
                        control.disabled = false;
                    });
                }, 2400);
            }
        });
    });
})();

(function () {
    var fileInputs = document.querySelectorAll('input[type="file"][data-accumulate-files="true"]');
    fileInputs.forEach(function (input) {
        var collector = new DataTransfer();
        input.addEventListener('change', function () {
            Array.prototype.forEach.call(input.files || [], function (file) {
                collector.items.add(file);
            });

            input.files = collector.files;
        });
    });
})();

(function () {
    var stack = document.getElementById('appToastStack');
    var flash = document.querySelector('[data-flash-message]');
    var compactStorageKey = 'jaxeops_compact_mode';
    var compactChoiceKey = 'jaxeops_compact_mode_choice';
    var roleProfile = (document.body.getAttribute('data-role-profile') || 'default').toLowerCase();

    function toast(type, message) {
        if (!stack || !message) {
            return;
        }

        var node = document.createElement('div');
        node.className = 'toast-item toast-' + (type || 'info');
        node.textContent = message;
        stack.appendChild(node);

        setTimeout(function () {
            node.classList.add('is-visible');
        }, 10);

        setTimeout(function () {
            node.classList.remove('is-visible');
            setTimeout(function () {
                if (node.parentNode) {
                    node.parentNode.removeChild(node);
                }
            }, 220);
        }, 2600);
    }

    window.AppUI = window.AppUI || {};
    window.AppUI.toast = toast;
    window.AppUI.roleProfile = roleProfile;

    // If user has never chosen density manually, apply role-specific defaults.
    if (!localStorage.getItem(compactChoiceKey)) {
        if (roleProfile === 'clientele' || roleProfile === 'prod') {
            localStorage.setItem(compactStorageKey, '1');
        } else if (roleProfile === 'cc') {
            localStorage.setItem(compactStorageKey, '0');
        }
    }

    if (flash) {
        toast(flash.getAttribute('data-flash-type') || 'info', flash.getAttribute('data-flash-message') || '');
    }

    if (localStorage.getItem(compactStorageKey) === '1') {
        document.body.classList.add('compact-mode');
    }

    document.querySelectorAll('[data-compact-toggle]').forEach(function (button) {
        var refreshLabel = function () {
            const label = document.body.classList.contains('compact-mode') ? 'Mode étendu' : 'Mode compact'; button.textContent = button.hasAttribute('data-icon-toggle') ? '▤' : label; button.title=label; button.setAttribute('aria-label',label);
        };
        refreshLabel();
        button.addEventListener('click', function () {
            document.body.classList.toggle('compact-mode');
            localStorage.setItem(compactStorageKey, document.body.classList.contains('compact-mode') ? '1' : '0');
            localStorage.setItem(compactChoiceKey, 'manual');
            refreshLabel();
        });
    });

    document.addEventListener('keydown', function (event) {
        var tag = (event.target && event.target.tagName) ? event.target.tagName.toLowerCase() : '';
        var editable = tag === 'input' || tag === 'textarea' || tag === 'select' || (event.target && event.target.isContentEditable);

        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
            event.preventDefault();
            var activeForm = event.target && event.target.closest ? event.target.closest('form') : null;
            if (!activeForm) {
                activeForm = document.querySelector('main form');
            }
            if (activeForm) {
                activeForm.requestSubmit();
            }
            return;
        }

        if (event.altKey && event.key.toLowerCase() === 'n' && !editable) {
            event.preventDefault();
            var nextLink = document.querySelector('[data-shortcut-next]');
            if (nextLink) {
                nextLink.click();
            }
            return;
        }

        if (event.altKey && event.key.toLowerCase() === 'p' && !editable) {
            event.preventDefault();
            var prevLink = document.querySelector('[data-shortcut-prev]');
            if (prevLink) {
                prevLink.click();
            }
            return;
        }

        if (event.key === 'Escape') {
            var openDetails = document.querySelectorAll('details[open]');
            if (openDetails.length > 0) {
                openDetails[openDetails.length - 1].removeAttribute('open');
            }
        }
    });
})();

(function () {
    var toggle = document.getElementById('mobileNavMoreToggle');
    var sheet = document.getElementById('mobileNavMoreSheet');
    if (!toggle || !sheet) {
        return;
    }

    function closeSheet() {
        toggle.setAttribute('aria-expanded', 'false');
        sheet.hidden = true;
        document.body.classList.remove('mobile-sheet-open');
    }

    function openSheet() {
        toggle.setAttribute('aria-expanded', 'true');
        sheet.hidden = false;
        document.body.classList.add('mobile-sheet-open');
    }

    toggle.addEventListener('click', function () {
        if (sheet.hidden) {
            openSheet();
        } else {
            closeSheet();
        }
    });

    document.addEventListener('click', function (event) {
        if (sheet.hidden) {
            return;
        }
        if (sheet.contains(event.target) || toggle.contains(event.target)) {
            return;
        }
        closeSheet();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSheet();
        }
    });
})();
</script>
<script src="<?= htmlspecialchars(app_url('/public/assets/calendar-position.js')) ?>"></script>
<?php if($currentUser): ?><button id="install-app" class="install-app" type="button" hidden>Installer Strax</button><?php endif ?>
<script src="<?= htmlspecialchars(app_url('/public/assets/reporting-workspace.js?v=1')) ?>"></script>
<script src="<?= htmlspecialchars(app_url('/public/assets/app-install.js?v=1')) ?>"></script>
</body>
</html>
