<?php

class PublicLegalContent {
    public static function privacy(): array {
        return [
            'eyebrow' => 'Protection des données',
            'title' => 'Politique de confidentialité',
            'intro' => 'Cette politique explique comment Strax, édité par Jaxe Communication, traite les données nécessaires à la gestion éditoriale, à la collaboration et aux connexions avec les réseaux sociaux.',
            'updated' => '25 août 2026',
            'sections' => [
                ['Données collectées', 'Nous traitons les informations de compte et d’organisation, les projets, calendriers, contenus, fichiers, validations, journaux de sécurité et paramètres d’intégration fournis par les utilisateurs. Lorsqu’un réseau social est connecté, nous recevons les identifiants des pages ou comptes autorisés, les jetons d’accès nécessaires et les données que la plateforme rend disponibles selon les permissions accordées.'],
                ['Finalités', 'Ces données servent à fournir le service Strax, organiser les équipes et les droits, préparer et publier des contenus, synchroniser les calendriers, collecter les indicateurs autorisés, sécuriser les accès, prévenir les abus et assurer l’assistance.'],
                ['Connexions Meta et autres plateformes', 'Strax n’accède qu’aux pages, comptes et fonctionnalités expressément autorisés pendant le parcours OAuth. Les jetons sont conservés de manière protégée et ne sont pas vendus. Une connexion peut être révoquée depuis Strax ou directement depuis les paramètres du fournisseur concerné.'],
                ['Partage et sous-traitants', 'Les données ne sont communiquées qu’aux membres autorisés de l’organisation, aux agences expressément habilitées par le client et aux prestataires techniques nécessaires au fonctionnement du service. Elles ne sont ni vendues ni utilisées pour du profilage publicitaire par Strax.'],
                ['Conservation et sécurité', 'Les données sont conservées pendant la durée du compte et selon les besoins contractuels, légaux, de sauvegarde et de sécurité. Strax applique notamment une séparation par organisation, des contrôles de droits, des journaux d’activité, le chiffrement des secrets et des protections contre les tentatives d’accès automatisées.'],
                ['Vos droits', 'Vous pouvez demander l’accès, la rectification, l’export ou la suppression de vos données, sous réserve des obligations légales et des droits des autres organisations. Pour exercer une demande, écrivez à jephte.k@jaxecommunication.com en précisant votre organisation et l’adresse du compte concerné.'],
                ['Contact', 'Responsable du service : Jaxe Communication. Contact confidentialité et sécurité : jephte.k@jaxecommunication.com.'],
            ],
        ];
    }

    public static function terms(): array {
        return [
            'eyebrow' => 'Cadre d’utilisation',
            'title' => 'Conditions d’utilisation',
            'intro' => 'Les présentes conditions encadrent l’accès à Strax et l’utilisation de ses fonctions de pilotage éditorial, collaboration, publication et analyse.',
            'updated' => '25 août 2026',
            'sections' => [
                ['Compte et organisation', 'Chaque utilisateur doit fournir des informations exactes, protéger ses identifiants et utiliser les droits qui lui ont été attribués. L’administrateur d’une entreprise ou d’une agence est responsable des invitations, autorisations et révocations réalisées dans son espace.'],
                ['Données et contenus', 'Le client conserve la propriété de ses contenus et données. Il garantit disposer des droits nécessaires sur les textes, images, vidéos, marques et autres éléments importés ou publiés avec Strax.'],
                ['Réseaux sociaux', 'L’utilisation des connecteurs est également soumise aux règles de Facebook, Instagram et des autres plateformes concernées. Une publication peut échouer ou être retardée en raison d’une permission, d’une limitation API, d’un contrôle de plateforme ou d’une interruption externe.'],
                ['Usages interdits', 'Il est interdit de contourner les contrôles d’accès, compromettre la sécurité du service, publier un contenu illicite ou trompeur, usurper un compte, extraire massivement des données sans autorisation ou utiliser Strax pour porter atteinte aux droits de tiers.'],
                ['Disponibilité et évolution', 'Jaxe Communication met en œuvre des moyens raisonnables pour assurer la disponibilité et la sécurité de Strax. Le service peut évoluer, être interrompu pour maintenance ou être adapté aux changements imposés par les fournisseurs d’API.'],
                ['Suspension et résiliation', 'Un accès peut être suspendu en cas de risque de sécurité, d’utilisation abusive, de non-respect des présentes conditions ou de fin de la relation contractuelle. Le client peut demander la fermeture de son compte et la restitution ou suppression des données applicables.'],
                ['Contact', 'Pour toute question relative à ces conditions : jephte.k@jaxecommunication.com.'],
            ],
        ];
    }

    public static function deletion(): array {
        return [
            'eyebrow' => 'Contrôle de vos données',
            'title' => 'Suppression des données utilisateur',
            'intro' => 'Vous pouvez révoquer une connexion sociale ou demander la suppression des données associées à votre compte Strax.',
            'updated' => '25 août 2026',
            'sections' => [
                ['Révoquer Meta immédiatement', 'Dans Strax, ouvrez Publication sociale, sélectionnez la connexion concernée puis utilisez l’action de révocation. Vous pouvez aussi retirer Strax depuis les intégrations professionnelles ou les paramètres Applications et sites web de votre compte Meta.'],
                ['Demander la suppression', 'Envoyez un e-mail à jephte.k@jaxecommunication.com avec l’objet « Suppression des données Strax ». Indiquez votre nom, votre adresse de compte, votre organisation et, si possible, l’identifiant de la page ou du compte social concerné. N’envoyez jamais de mot de passe ni de jeton d’accès.'],
                ['Vérification et délai', 'Nous pouvons demander une vérification raisonnable afin d’éviter la suppression frauduleuse de données. Après validation, les données concernées sont supprimées ou anonymisées dans les systèmes actifs dans un délai cible de 30 jours. Les sauvegardes résiduelles sont éliminées selon leur cycle de rétention.'],
                ['Limites', 'Certaines informations peuvent être conservées lorsqu’elles sont nécessaires au respect d’une obligation légale, à la sécurité, à la prévention des fraudes, à la résolution d’un litige ou à la protection des droits d’une autre organisation.'],
                ['Confirmation', 'Une confirmation est envoyée à l’adresse vérifiée lorsque la demande a été traitée. Pour suivre une demande : jephte.k@jaxecommunication.com.'],
            ],
        ];
    }
}

