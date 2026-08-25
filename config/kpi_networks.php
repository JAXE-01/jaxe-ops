<?php
return [
    'facebook' => [
        'label' => 'Facebook',
        'weights' => [
            'engagement_rate' => 0.4,
            'reach' => 0.3,
            'clicks' => 0.3,
        ],
        'kpis' => [
            ['name' => 'reach', 'label' => 'Reach', 'type' => 'integer', 'column' => 'couverture', 'placeholder' => 'Nombre de comptes touches'],
            ['name' => 'impressions', 'label' => 'Impressions', 'type' => 'integer', 'column' => 'impressions', 'placeholder' => 'Nombre total d impressions'],
            ['name' => 'video_views', 'label' => 'Vues video', 'type' => 'integer', 'column' => 'vues', 'placeholder' => 'Vues 3s / 15s selon format'],
            ['name' => 'clicks', 'label' => 'Clics', 'type' => 'integer', 'column' => 'clics', 'placeholder' => 'Clics sur lien / profil'],
            ['name' => 'reactions', 'label' => 'Reactions', 'type' => 'integer', 'column' => 'likes', 'placeholder' => 'J aime, j adore, etc.'],
            ['name' => 'comments', 'label' => 'Commentaires', 'type' => 'integer', 'column' => 'commentaires', 'placeholder' => 'Nombre de commentaires'],
            ['name' => 'shares', 'label' => 'Partages', 'type' => 'integer', 'column' => 'partages', 'placeholder' => 'Partages / republications'],
            ['name' => 'engagement_rate', 'label' => 'Engagement rate (%)', 'type' => 'float', 'column' => 'engagement_rate', 'placeholder' => 'Ex: 4.75'],
        ],
    ],
    'instagram' => [
        'label' => 'Instagram',
        'weights' => [
            'engagement_rate' => 0.4,
            'reach' => 0.3,
            'clicks' => 0.3,
        ],
        'kpis' => [
            ['name' => 'impressions', 'label' => 'Impressions', 'type' => 'integer', 'column' => 'impressions', 'placeholder' => 'Nombre total d impressions'],
            ['name' => 'reach', 'label' => 'Reach', 'type' => 'integer', 'column' => 'couverture', 'placeholder' => 'Comptes touches'],
            ['name' => 'video_views', 'label' => 'Vues video', 'type' => 'integer', 'column' => 'vues', 'placeholder' => 'Vues reels / videos'],
            ['name' => 'likes', 'label' => 'Likes', 'type' => 'integer', 'column' => 'likes', 'placeholder' => 'J aime'],
            ['name' => 'comments', 'label' => 'Commentaires', 'type' => 'integer', 'column' => 'commentaires', 'placeholder' => 'Commentaires'],
            ['name' => 'shares', 'label' => 'Partages', 'type' => 'integer', 'column' => 'partages', 'placeholder' => 'Partages'],
            ['name' => 'saves', 'label' => 'Saves', 'type' => 'integer', 'column' => 'sauvegardes', 'placeholder' => 'Enregistrements'],
            ['name' => 'profile_visits', 'label' => 'Visites profil', 'type' => 'integer', 'placeholder' => 'Visites du profil'],
            ['name' => 'engagement_rate', 'label' => 'Engagement rate (%)', 'type' => 'float', 'column' => 'engagement_rate', 'placeholder' => 'Ex: 6.20'],
        ],
    ],
    'linkedin' => [
        'label' => 'LinkedIn',
        'weights' => [
            'engagement_rate' => 0.4,
            'reach' => 0.3,
            'clicks' => 0.3,
        ],
        'kpis' => [
            ['name' => 'impressions', 'label' => 'Impressions', 'type' => 'integer', 'column' => 'impressions', 'placeholder' => 'Impressions publication'],
            ['name' => 'reach', 'label' => 'Reach', 'type' => 'integer', 'column' => 'couverture', 'placeholder' => 'Membres touches'],
            ['name' => 'clicks', 'label' => 'Clics', 'type' => 'integer', 'column' => 'clics', 'placeholder' => 'Clics sur contenu'],
            ['name' => 'ctr', 'label' => 'CTR (%)', 'type' => 'float', 'column' => 'ctr', 'placeholder' => 'Ex: 1.45'],
            ['name' => 'reactions', 'label' => 'Reactions', 'type' => 'integer', 'column' => 'likes', 'placeholder' => 'Reactions'],
            ['name' => 'comments', 'label' => 'Commentaires', 'type' => 'integer', 'column' => 'commentaires', 'placeholder' => 'Commentaires'],
            ['name' => 'shares', 'label' => 'Partages', 'type' => 'integer', 'column' => 'partages', 'placeholder' => 'Repartages'],
            ['name' => 'engagement_rate', 'label' => 'Engagement rate (%)', 'type' => 'float', 'column' => 'engagement_rate', 'placeholder' => 'Ex: 3.10'],
        ],
    ],
    'tiktok' => [
        'label' => 'TikTok',
        'weights' => [
            'engagement_rate' => 0.4,
            'video_views' => 0.35,
            'clicks' => 0.25,
        ],
        'kpis' => [
            ['name' => 'video_views', 'label' => 'Vues video', 'type' => 'integer', 'column' => 'vues', 'placeholder' => 'Vues video'],
            ['name' => 'reach', 'label' => 'Reach', 'type' => 'integer', 'column' => 'couverture', 'placeholder' => 'Comptes touches'],
            ['name' => 'likes', 'label' => 'Likes', 'type' => 'integer', 'column' => 'likes', 'placeholder' => 'Likes'],
            ['name' => 'comments', 'label' => 'Commentaires', 'type' => 'integer', 'column' => 'commentaires', 'placeholder' => 'Commentaires'],
            ['name' => 'shares', 'label' => 'Partages', 'type' => 'integer', 'column' => 'partages', 'placeholder' => 'Partages'],
            ['name' => 'avg_watch_time', 'label' => 'Temps moyen visionnage (s)', 'type' => 'float', 'column' => 'avg_watch_time', 'placeholder' => 'Ex: 8.6'],
            ['name' => 'profile_views', 'label' => 'Vues profil', 'type' => 'integer', 'placeholder' => 'Visites profil'],
            ['name' => 'clicks', 'label' => 'Clics bio/lien', 'type' => 'integer', 'column' => 'clics', 'placeholder' => 'Clics'],
            ['name' => 'engagement_rate', 'label' => 'Engagement rate (%)', 'type' => 'float', 'column' => 'engagement_rate', 'placeholder' => 'Ex: 9.40'],
        ],
    ],
];
