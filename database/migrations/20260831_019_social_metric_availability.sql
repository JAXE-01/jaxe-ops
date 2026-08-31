-- NULL means unavailable from the provider; zero remains a measured zero.
ALTER TABLE reporting_metrics
    MODIFY impressions INT NULL DEFAULT NULL,
    MODIFY couverture INT NULL DEFAULT NULL,
    MODIFY vues INT NULL DEFAULT NULL,
    MODIFY likes INT NULL DEFAULT NULL,
    MODIFY commentaires INT NULL DEFAULT NULL,
    MODIFY partages INT NULL DEFAULT NULL,
    MODIFY clics INT NULL DEFAULT NULL,
    MODIFY sauvegardes INT NULL DEFAULT NULL,
    MODIFY abonnes_gagnes INT NULL DEFAULT NULL;
