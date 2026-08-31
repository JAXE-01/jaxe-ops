<?php
class ReportingMetricModel extends Model {
    public function getNetworkKpiConfig() {
        return (new SettingsModel())->getKpiNetworksConfig();
    }

    public function getCampaignOptions() {
        $scope=AgencyAccessPolicy::clientSqlScope('cl','analytics','analytics_campaigns');
        $sql = 'SELECT c.id, c.nom, cl.nom AS client_nom FROM campagnes c JOIN clients cl ON cl.id=c.client_id WHERE '.$scope['sql'].' ORDER BY c.nom ASC';
        $stmt = $this->db->prepare($sql);$stmt->execute($scope['params']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $options = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $label = trim((string) ($row['nom'] ?? 'Campagne'));
            $client = trim((string) ($row['client_nom'] ?? ''));
            if ($client !== '') {
                $label .= ' - ' . $client;
            }
            $options[$id] = $label;
        }

        return $options;
    }

    public function getPublicationOptions($campaignId = 0) {
        $sql = 'SELECT ct.id, ct.sujet, cp.nom AS campagne_nom
            FROM contenus ct
            LEFT JOIN campagnes cp ON cp.id = ct.campagne_id
            LEFT JOIN projets pr ON pr.id = ct.projet_id
            JOIN clients scope_client ON scope_client.id=COALESCE(cp.client_id,pr.client_id)
            WHERE (:campagne_id = 0 OR ct.campagne_id = :campagne_id) AND __ANALYTICS_PUBLICATION_SCOPE__
            ORDER BY ct.id DESC';

        $scope=AgencyAccessPolicy::clientSqlScope('scope_client','analytics','analytics_publications');
        $sql=str_replace('__ANALYTICS_PUBLICATION_SCOPE__',$scope['sql'],$sql);
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge(['campagne_id' => (int) $campaignId],$scope['params']));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $options = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $subject = trim((string) ($row['sujet'] ?? 'Publication'));
            $campaign = trim((string) ($row['campagne_nom'] ?? ''));
            $options[$id] = $campaign !== '' ? ($subject . ' - ' . $campaign) : $subject;
        }

        return $options;
    }

    public function getSocialPublicationOptions($campaignId = 0) {
        $scope = AgencyAccessPolicy::clientSqlScope('scope_client', 'analytics', 'analytics_social_publications');
        $sql = 'SELECT sp.id, sp.master_title, scope_client.entreprise AS client_nom
            FROM social_publications sp
            JOIN clients scope_client ON scope_client.id = sp.client_id
            LEFT JOIN projets pr ON pr.id = sp.project_id
            WHERE sp.tenant_id = :tenant
              AND (:campagne_id = 0 OR pr.campagne_id = :campagne_id)
              AND ' . $scope['sql'] . '
            ORDER BY sp.id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([
            'tenant' => TenantGuard::tenantId(),
            'campagne_id' => (int) $campaignId,
        ], $scope['params']));

        $options = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) continue;
            $title = trim((string) ($row['master_title'] ?? 'Publication sociale'));
            $client = trim((string) ($row['client_nom'] ?? ''));
            $options['social-' . $id] = $client !== '' ? ($title . ' - ' . $client) : $title;
        }
        return $options;
    }

    public function getPlatformOptions() {
        $config = $this->getNetworkKpiConfig();
        $options = [];
        foreach ($config as $networkKey => $meta) {
            $options[(string) $networkKey] = (string) ($meta['label'] ?? ucfirst((string) $networkKey));
        }
        return $options;
    }

    public function getClientOptions(): array {
        $scope=AgencyAccessPolicy::clientSqlScope('c','analytics','analytics_metric_clients');
        $stmt=$this->db->prepare('SELECT c.id,c.entreprise FROM clients c WHERE '.$scope['sql'].' ORDER BY c.entreprise');$stmt->execute($scope['params']);
        $options=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC)as$row)$options[(int)$row['id']]=(string)$row['entreprise'];return$options;
    }

    public function getPageOptions(int $clientId=0): array {
        $scope=AgencyAccessPolicy::clientSqlScope('c','analytics','analytics_metric_pages');
        $stmt=$this->db->prepare('SELECT sc.id,sc.account_label,c.entreprise client_name FROM social_connections sc JOIN clients c ON c.id=sc.client_id WHERE sc.tenant_id=:tenant AND sc.status="Connected" AND (:client=0 OR sc.client_id=:client) AND '.$scope['sql'].' ORDER BY c.entreprise,sc.account_label');
        $stmt->execute(array_merge(['tenant'=>TenantGuard::tenantId(),'client'=>$clientId],$scope['params']));$options=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC)as$row)$options[(int)$row['id']]=(string)$row['client_name'].' · '.(string)$row['account_label'];return$options;
    }

    public function normalizePayload(array $data) {
        $network = strtolower(trim((string) ($data['plateforme'] ?? '')));
        $config = $this->getNetworkKpiConfig();
        $networkMeta = $config[$network] ?? null;
        if (!is_array($networkMeta)) {
            throw new RuntimeException('Reseau non pris en charge.');
        }

        $dateCollecte = trim((string) ($data['date_collecte'] ?? ''));
        if ($dateCollecte === '') {
            $dateCollecte = date('Y-m-d');
        }
        $data['date_collecte'] = $dateCollecte;

        $rawKpis = is_array($data['kpi_values'] ?? null) ? $data['kpi_values'] : [];
        $kpiPayload = [];
        $columnValues = [
            'impressions' => 0,
            'couverture' => 0,
            'vues' => 0,
            'likes' => 0,
            'commentaires' => 0,
            'partages' => 0,
            'clics' => 0,
            'sauvegardes' => 0,
            'abonnes_gagnes' => 0,
            'ctr' => null,
            'engagement_rate' => null,
            'avg_watch_time' => null,
        ];

        foreach ((array) ($networkMeta['kpis'] ?? []) as $kpi) {
            $name = trim((string) ($kpi['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $type = strtolower((string) ($kpi['type'] ?? 'integer'));
            $rawValue = $rawKpis[$name] ?? 0;
            $normalizedValue = $type === 'float' ? (float) $rawValue : (int) $rawValue;

            if ($type === 'integer') {
                $normalizedValue = max(0, (int) $normalizedValue);
            }

            $kpiPayload[$name] = $normalizedValue;

            $column = trim((string) ($kpi['column'] ?? ''));
            if ($column !== '' && array_key_exists($column, $columnValues)) {
                $columnValues[$column] = $normalizedValue;
            }
        }

        $data['plateforme'] = $network;
        $data['kpi_payload'] = json_encode($kpiPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        foreach ($columnValues as $column => $value) {
            $data[$column] = $value;
        }

        return $data;
    }

    public function createMetric(array $data) {
        $campaignId=(int)($data['campagne_id']??0);
        $contentId=(int)($data['contenu_id']??0);
        $projectId=null;
        if($contentId>0){
            $context=$this->db->prepare('SELECT campagne_id,projet_id FROM contenus WHERE id=:id LIMIT 1');
            $context->execute(['id'=>$contentId]);
            $row=$context->fetch(PDO::FETCH_ASSOC)?:[];
            $campaignId=$campaignId?:((int)($row['campagne_id']??0));
            $projectId=(int)($row['projet_id']??0)?:null;
        }
        if($campaignId>0)$this->assertCampaignAnalyticsAccess($campaignId,true);
        elseif($projectId)TenantGuard::assertProject($projectId);
        else throw new RuntimeException('Publication ou campagne inaccessible.');
        $sql = 'INSERT INTO reporting_metrics (
                tenant_id,
                campagne_id,
                project_id,
                contenu_id,
                source,
                plateforme,
                date_collecte,
                impressions,
                couverture,
                vues,
                likes,
                commentaires,
                partages,
                clics,
                ctr,
                engagement_rate,
                avg_watch_time,
                sauvegardes,
                abonnes_gagnes,
                kpi_payload,
                url_publication
            ) VALUES (
                :tenant_id,
                :campagne_id,
                :project_id,
                :contenu_id,
                "manual",
                :plateforme,
                :date_collecte,
                :impressions,
                :couverture,
                :vues,
                :likes,
                :commentaires,
                :partages,
                :clics,
                :ctr,
                :engagement_rate,
                :avg_watch_time,
                :sauvegardes,
                :abonnes_gagnes,
                :kpi_payload,
                :url_publication
            )';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => TenantGuard::tenantId(),
            'campagne_id' => $campaignId ?: null,
            'project_id' => $projectId,
            'contenu_id' => !empty($data['contenu_id']) ? (int) $data['contenu_id'] : null,
            'plateforme' => (string) ($data['plateforme'] ?? ''),
            'date_collecte' => (string) ($data['date_collecte'] ?? null),
            'impressions' => max(0, (int) ($data['impressions'] ?? 0)),
            'couverture' => max(0, (int) ($data['couverture'] ?? 0)),
            'vues' => max(0, (int) ($data['vues'] ?? 0)),
            'likes' => max(0, (int) ($data['likes'] ?? 0)),
            'commentaires' => max(0, (int) ($data['commentaires'] ?? 0)),
            'partages' => max(0, (int) ($data['partages'] ?? 0)),
            'clics' => max(0, (int) ($data['clics'] ?? 0)),
            'ctr' => isset($data['ctr']) ? (float) $data['ctr'] : null,
            'engagement_rate' => isset($data['engagement_rate']) ? (float) $data['engagement_rate'] : null,
            'avg_watch_time' => isset($data['avg_watch_time']) ? (float) $data['avg_watch_time'] : null,
            'sauvegardes' => max(0, (int) ($data['sauvegardes'] ?? 0)),
            'abonnes_gagnes' => max(0, (int) ($data['abonnes_gagnes'] ?? 0)),
            'kpi_payload' => (string) ($data['kpi_payload'] ?? '{}'),
            'url_publication' => trim((string) ($data['url_publication'] ?? '')) !== '' ? trim((string) $data['url_publication']) : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function deleteMetric($id) {
        $this->assertMetricAnalyticsAccess((int)$id,true);
        $stmt = $this->db->prepare('DELETE FROM reporting_metrics WHERE id = :id');
        $stmt->execute(['id' => (int) $id]);
    }

    public function getMetrics(array $filters = [], $limit = 500) {
        $params = [];
        $where = $this->buildFiltersWhere($filters, $params);

        $sql = 'SELECT rm.*, c.nom AS campagne_nom,
            COALESCE(ct.sujet, sp.master_title, "Publication non rattachee") AS publication_titre,
            COALESCE(DATE((SELECT spt.published_at FROM social_publication_targets spt WHERE spt.id=rm.social_target_id)),rm.date_collecte) AS date_publication,
            (SELECT scl.entreprise FROM social_publication_targets spt JOIN social_connections sc ON sc.id=spt.connection_id JOIN clients scl ON scl.id=sc.client_id WHERE spt.id=rm.social_target_id) AS client_nom,
            (SELECT sc.account_label FROM social_publication_targets spt JOIN social_connections sc ON sc.id=spt.connection_id WHERE spt.id=rm.social_target_id) AS page_nom,
            COALESCE(CAST(rm.contenu_id AS CHAR), CONCAT("social-", rm.social_publication_id), CONCAT("manual-", rm.id)) AS publication_id,
                DATE_FORMAT(COALESCE(DATE((SELECT ppt.published_at FROM social_publication_targets ppt WHERE ppt.id=rm.social_target_id)),rm.date_collecte), "%Y-%m") AS periode_analysee
            FROM reporting_metrics rm
            LEFT JOIN campagnes c ON c.id = rm.campagne_id
            LEFT JOIN contenus ct ON ct.id = rm.contenu_id
            LEFT JOIN social_publications sp ON sp.id = rm.social_publication_id
            ' . $where . '
            ORDER BY rm.date_collecte DESC, rm.id DESC
            LIMIT ' . max(1, (int) $limit);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getGrowthSeries(array $filters = []) {
        $params = [];
        $where = $this->buildFiltersWhere($filters, $params, true);

        $sql = 'SELECT
                rm.date_collecte,
                SUM(rm.impressions) AS impressions,
                SUM(rm.couverture) AS couverture,
                SUM(rm.vues) AS vues,
                SUM(rm.likes) AS likes,
                SUM(rm.commentaires) AS commentaires,
                SUM(rm.partages) AS partages,
                SUM(COALESCE(rm.clics, 0)) AS clics,
                AVG(COALESCE(rm.ctr, 0)) AS ctr,
                AVG(COALESCE(rm.engagement_rate, 0)) AS engagement_rate,
                SUM(rm.sauvegardes) AS sauvegardes,
                SUM(rm.abonnes_gagnes) AS abonnes_gagnes
            FROM reporting_metrics rm
            ' . $where . '
            GROUP BY rm.date_collecte
            ORDER BY rm.date_collecte ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $scoreByDate = [];
        foreach ($this->getEnrichedMetrics($filters, 5000) as $metricRow) {
            $dateKey = (string) ($metricRow['date_collecte'] ?? '');
            if ($dateKey === '') {
                continue;
            }
            if (!isset($scoreByDate[$dateKey])) {
                $scoreByDate[$dateKey] = ['score_sum' => 0.0, 'growth_sum' => 0.0, 'count' => 0];
            }
            $scoreByDate[$dateKey]['score_sum'] += (float) ($metricRow['score_global'] ?? 0);
            $scoreByDate[$dateKey]['growth_sum'] += (float) ($metricRow['growth_rate'] ?? 0);
            $scoreByDate[$dateKey]['count']++;
        }

        foreach ($rows as &$row) {
            $impressions = max(0, (int) ($row['impressions'] ?? 0));
            $interactions =
                max(0, (int) ($row['likes'] ?? 0)) +
                max(0, (int) ($row['commentaires'] ?? 0)) +
                max(0, (int) ($row['partages'] ?? 0)) +
                max(0, (int) ($row['sauvegardes'] ?? 0));
            $row['interactions'] = $interactions;
            $calcRate = $impressions > 0 ? round(($interactions / $impressions) * 100, 2) : 0;
            $row['engagement_rate'] = (float) ($row['engagement_rate'] ?? 0) > 0 ? (float) $row['engagement_rate'] : $calcRate;

            $dateKey = (string) ($row['date_collecte'] ?? '');
            $scoreMeta = $scoreByDate[$dateKey] ?? ['score_sum' => 0.0, 'growth_sum' => 0.0, 'count' => 0];
            $row['score_global'] = $scoreMeta['count'] > 0 ? round($scoreMeta['score_sum'] / $scoreMeta['count'], 2) : 0.0;
            $row['growth_rate'] = $scoreMeta['count'] > 0 ? round($scoreMeta['growth_sum'] / $scoreMeta['count'], 2) : 0.0;
        }
        unset($row);

        return $rows;
    }

    public function getEnrichedMetrics(array $filters = [], $limit = 5000) {
        $rows = $this->getMetrics($filters, $limit);
        return $this->enrichMetricRows($rows);
    }

    public function getDashboardAnalysis(array $filters = []) {
        $rows = $this->getEnrichedMetrics($filters, 5000);
        if (empty($rows)) {
            return [
                'cards' => [
                    'collectes' => 0,
                    'score_moyen' => 0.0,
                    'growth_moyen' => 0.0,
                    'daily_moyen' => 0.0,
                ],
                'line_series' => [],
                'network_comparison' => [],
                'top_publications' => [],
                'weak_publications' => [],
                'global_insights' => [],
            ];
        }

        $scoreSum = 0.0;
        $growthSum = 0.0;
        $dailySum = 0.0;
        $lineBuckets = [];
        $networkBuckets = [];
        $publicationBuckets = [];

        foreach ($rows as $row) {
            $score = (float) ($row['score_global'] ?? 0);
            $growth = (float) ($row['growth_rate'] ?? 0);
            $daily = (float) ($row['daily_rate'] ?? 0);
            $scoreSum += $score;
            $growthSum += $growth;
            $dailySum += $daily;

            $dateKey = (string) ($row['date_collecte'] ?? '');
            if ($dateKey !== '') {
                if (!isset($lineBuckets[$dateKey])) {
                    $lineBuckets[$dateKey] = ['score_sum' => 0.0, 'count' => 0];
                }
                $lineBuckets[$dateKey]['score_sum'] += $score;
                $lineBuckets[$dateKey]['count']++;
            }

            $network = strtolower((string) ($row['plateforme'] ?? ''));
            $networkLabel = $this->resolveNetworkLabel($network);
            if (!isset($networkBuckets[$network])) {
                $networkBuckets[$network] = [
                    'reseau' => $network,
                    'reseau_label' => $networkLabel,
                    'collectes' => 0,
                    'score_sum' => 0.0,
                    'growth_sum' => 0.0,
                    'kpi_totals' => [],
                ];
            }
            $networkBuckets[$network]['collectes']++;
            $networkBuckets[$network]['score_sum'] += $score;
            $networkBuckets[$network]['growth_sum'] += $growth;

            $kpis = $this->parseKpiPayload((string) ($row['kpi_payload'] ?? '{}'));
            foreach ($kpis as $kpiName => $kpiValue) {
                if (!isset($networkBuckets[$network]['kpi_totals'][$kpiName])) {
                    $networkBuckets[$network]['kpi_totals'][$kpiName] = 0.0;
                }
                $networkBuckets[$network]['kpi_totals'][$kpiName] += (float) $kpiValue;
            }

            $publicationKey = (string) ($row['publication_id'] ?? '');
            if (!isset($publicationBuckets[$publicationKey])) {
                $publicationBuckets[$publicationKey] = [
                    'publication_id' => $publicationKey,
                    'publication_titre' => (string) ($row['publication_titre'] ?? 'Publication non rattachee'),
                    'collectes' => 0,
                    'score_sum' => 0.0,
                    'last_score' => 0.0,
                    'last_date' => '',
                    'network_scores' => [],
                ];
            }
            $publicationBuckets[$publicationKey]['collectes']++;
            $publicationBuckets[$publicationKey]['score_sum'] += $score;
            if ((string) ($row['date_collecte'] ?? '') >= (string) $publicationBuckets[$publicationKey]['last_date']) {
                $publicationBuckets[$publicationKey]['last_date'] = (string) ($row['date_collecte'] ?? '');
                $publicationBuckets[$publicationKey]['last_score'] = $score;
            }
            if (!isset($publicationBuckets[$publicationKey]['network_scores'][$network])) {
                $publicationBuckets[$publicationKey]['network_scores'][$network] = ['sum' => 0.0, 'count' => 0];
            }
            $publicationBuckets[$publicationKey]['network_scores'][$network]['sum'] += $score;
            $publicationBuckets[$publicationKey]['network_scores'][$network]['count']++;
        }

        ksort($lineBuckets);
        $lineSeries = [];
        foreach ($lineBuckets as $date => $bucket) {
            $lineSeries[] = [
                'date_collecte' => $date,
                'score_global' => $bucket['count'] > 0 ? round($bucket['score_sum'] / $bucket['count'], 2) : 0.0,
            ];
        }

        $networkComparison = [];
        foreach ($networkBuckets as $bucket) {
            $collectes = max(1, (int) $bucket['collectes']);
            $kpiAverages = [];
            foreach ((array) ($bucket['kpi_totals'] ?? []) as $kpiName => $total) {
                $kpiAverages[$kpiName] = round(((float) $total) / $collectes, 2);
            }
            $networkComparison[] = [
                'reseau' => (string) ($bucket['reseau'] ?? ''),
                'reseau_label' => (string) ($bucket['reseau_label'] ?? ''),
                'collectes' => (int) ($bucket['collectes'] ?? 0),
                'performance_globale' => round(((float) ($bucket['score_sum'] ?? 0)) / $collectes, 2),
                'growth_moyen' => round(((float) ($bucket['growth_sum'] ?? 0)) / $collectes, 2),
                'kpi_moyennes' => $kpiAverages,
            ];
        }

        usort($networkComparison, static function ($left, $right) {
            return (float) ($right['performance_globale'] ?? 0) <=> (float) ($left['performance_globale'] ?? 0);
        });

        $publicationStats = [];
        foreach ($publicationBuckets as $bucket) {
            $collectes = max(1, (int) $bucket['collectes']);
            $avgScore = round(((float) ($bucket['score_sum'] ?? 0)) / $collectes, 2);
            $bestNetworkLabel = '';
            $bestNetworkScore = -INF;
            foreach ((array) ($bucket['network_scores'] ?? []) as $network => $networkScoreMeta) {
                $networkAvg = ((float) ($networkScoreMeta['sum'] ?? 0)) / max(1, (int) ($networkScoreMeta['count'] ?? 0));
                if ($networkAvg > $bestNetworkScore) {
                    $bestNetworkScore = $networkAvg;
                    $bestNetworkLabel = $this->resolveNetworkLabel((string) $network);
                }
            }

            $publicationStats[] = [
                'publication_id' => (string) ($bucket['publication_id'] ?? ''),
                'publication_titre' => (string) ($bucket['publication_titre'] ?? ''),
                'collectes' => (int) ($bucket['collectes'] ?? 0),
                'score_moyen' => $avgScore,
                'score_recent' => round((float) ($bucket['last_score'] ?? 0), 2),
                'best_network' => $bestNetworkLabel,
            ];
        }

        usort($publicationStats, static function ($left, $right) {
            return (float) ($right['score_moyen'] ?? 0) <=> (float) ($left['score_moyen'] ?? 0);
        });

        $topPublications = array_slice($publicationStats, 0, 3);
        $weakPublications = array_slice(array_reverse($publicationStats), 0, 3);

        $avgScore = round($scoreSum / max(1, count($rows)), 2);
        $avgGrowth = round($growthSum / max(1, count($rows)), 2);
        $avgDaily = round($dailySum / max(1, count($rows)), 2);

        $globalInsights = [];
        if ($avgScore >= 70) {
            $globalInsights[] = 'Performance globale solide sur la periode selectionnee.';
        } elseif ($avgScore < 45) {
            $globalInsights[] = 'Niveau global faible: renforcer le contenu et les CTA.';
        }
        if ($avgGrowth < 2) {
            $globalInsights[] = 'Croissance moyenne faible: la progression entre collectes reste limitee.';
        }
        if (!empty($networkComparison)) {
            $globalInsights[] = 'Reseau dominant: ' . (string) ($networkComparison[0]['reseau_label'] ?? $networkComparison[0]['reseau'] ?? 'N/A') . '.';
        }

        return [
            'cards' => [
                'collectes' => count($rows),
                'score_moyen' => $avgScore,
                'growth_moyen' => $avgGrowth,
                'daily_moyen' => $avgDaily,
            ],
            'line_series' => $lineSeries,
            'network_comparison' => $networkComparison,
            'top_publications' => $topPublications,
            'weak_publications' => $weakPublications,
            'global_insights' => array_values(array_unique($globalInsights)),
        ];
    }

    public function getExcelFlatRows(array $filters = []) {
        $rows = $this->getEnrichedMetrics($filters, 5000);
        $flat = [];

        foreach ($rows as $row) {
            $kpis = $this->parseKpiPayload((string) ($row['kpi_payload'] ?? '{}'));
            $growthByKpi = is_array($row['kpi_growth_map'] ?? null) ? $row['kpi_growth_map'] : [];
            $dailyByKpi = is_array($row['kpi_daily_map'] ?? null) ? $row['kpi_daily_map'] : [];
            foreach ($kpis as $kpiName => $kpiValue) {
                $flat[] = [
                    'Date_collecte' => (string) ($row['date_collecte'] ?? ''),
                    'Date_publication' => (string)($row['date_publication']??''),
                    'Client' => (string)($row['client_nom']??''),
                    'Page' => (string)($row['page_nom']??''),
                    'Publication_ID' => (string) ($row['publication_id'] ?? ''),
                    'Reseau' => (string) ($row['plateforme'] ?? ''),
                    'KPI' => (string) $kpiName,
                    'Valeur' => (float) $kpiValue,
                    'Growth_rate' => round((float) ($growthByKpi[$kpiName] ?? 0), 4),
                    'Daily_rate' => round((float) ($dailyByKpi[$kpiName] ?? 0), 4),
                ];
            }
        }

        return $flat;
    }

    public function getImpactStats(array $filters = []) {
        $params = [];
        $where = $this->buildFiltersWhere($filters, $params);
        $sql = 'SELECT vues, impressions, COALESCE(couverture, 0) AS couverture, likes, commentaires, partages, COALESCE(clics, 0) AS clics, COALESCE(ctr, 0) AS ctr, COALESCE(engagement_rate, 0) AS engagement_rate, sauvegardes, abonnes_gagnes
            FROM reporting_metrics rm
            ' . $where;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = array_values(array_filter($stmt->fetchAll(PDO::FETCH_ASSOC),static fn($row)=>$row['vues']!==null));

        $yValues = [];
        foreach ($rows as $row) {
            $yValues[] = (float) $row['vues'];
        }

        $metrics = [
            'impressions' => 'Impressions',
            'couverture' => 'Couverture',
            'likes' => 'Likes',
            'commentaires' => 'Commentaires',
            'partages' => 'Partages',
            'clics' => 'Clics',
            'sauvegardes' => 'Sauvegardes',
            'abonnes_gagnes' => 'Abonnes gagnes',
            'ctr' => 'CTR',
            'engagement_rate' => 'Engagement rate',
        ];

        $correlations = [];
        foreach ($metrics as $key => $label) {
            $xValues = [];
            foreach ($rows as $row) {
                $xValues[] = (float) ($row[$key] ?? 0);
            }
            $correlations[] = [
                'metric' => $key,
                'label' => $label,
                'correlation' => $this->pearsonCorrelation($xValues, $yValues),
            ];
        }

        usort($correlations, static function ($left, $right) {
            return abs((float) ($right['correlation'] ?? 0)) <=> abs((float) ($left['correlation'] ?? 0));
        });

        return [
            'sample_size' => count($rows),
            'avg_views' => count($yValues) > 0 ? round(array_sum($yValues) / count($yValues), 1) : 0,
            'correlations' => $correlations,
        ];
    }

    private function buildFiltersWhere(array $filters, array &$params, $requireDate = false) {
        $clauses = [];
        $scope=AgencyAccessPolicy::clientSqlScope('scope_client','analytics','analytics_metrics');
        $projectScope=AgencyAccessPolicy::clientSqlScope('project_client','analytics','analytics_projects');
        $socialScope=AgencyAccessPolicy::clientSqlScope('social_client','analytics','analytics_social_metrics');
        $clauses[]='(EXISTS (SELECT 1 FROM campagnes scope_campaign JOIN clients scope_client ON scope_client.id=scope_campaign.client_id WHERE scope_campaign.id=rm.campagne_id AND '.$scope['sql'].') OR EXISTS (SELECT 1 FROM projets scope_project JOIN clients project_client ON project_client.id=scope_project.client_id WHERE scope_project.id=rm.project_id AND '.$projectScope['sql'].') OR EXISTS (SELECT 1 FROM social_publications scope_social JOIN clients social_client ON social_client.id=scope_social.client_id WHERE scope_social.id=rm.social_publication_id AND scope_social.tenant_id=rm.tenant_id AND '.$socialScope['sql'].'))';
        $params=array_merge($params,$scope['params'],$projectScope['params'],$socialScope['params']);

        $campagneId = (int) ($filters['campagne_id'] ?? 0);
        if ($campagneId > 0) {
            $clauses[] = 'rm.campagne_id = :campagne_id';
            $params['campagne_id'] = $campagneId;
        }

        $contenuId = (int) ($filters['contenu_id'] ?? 0);
        if ($contenuId > 0) {
            $clauses[] = 'rm.contenu_id = :contenu_id';
            $params['contenu_id'] = $contenuId;
        }

        $socialPublicationId = (int) ($filters['social_publication_id'] ?? 0);
        if ($socialPublicationId > 0) {
            $clauses[] = 'rm.social_publication_id = :social_publication_id';
            $params['social_publication_id'] = $socialPublicationId;
        }

        $plateforme = trim((string) ($filters['plateforme'] ?? ''));
        if ($plateforme !== '') {
            $clauses[] = 'rm.plateforme = :plateforme';
            $params['plateforme'] = $plateforme;
        }

        $clientId=(int)($filters['client_id']??0);
        if($clientId>0){$clauses[]='EXISTS (SELECT 1 FROM social_publication_targets ft JOIN social_connections fc ON fc.id=ft.connection_id WHERE ft.id=rm.social_target_id AND fc.client_id=:filter_client)';$params['filter_client']=$clientId;}
        $connectionId=(int)($filters['connection_id']??0);
        if($connectionId>0){$clauses[]='EXISTS (SELECT 1 FROM social_publication_targets ft WHERE ft.id=rm.social_target_id AND ft.connection_id=:filter_connection)';$params['filter_connection']=$connectionId;}

        $from = trim((string) ($filters['from'] ?? ''));
        if ($from !== '') {
            $clauses[] = 'COALESCE(DATE((SELECT fpt.published_at FROM social_publication_targets fpt WHERE fpt.id=rm.social_target_id)),rm.date_collecte) >= :date_from';
            $params['date_from'] = $from;
        }

        $to = trim((string) ($filters['to'] ?? ''));
        if ($to !== '') {
            $clauses[] = 'COALESCE(DATE((SELECT fpt.published_at FROM social_publication_targets fpt WHERE fpt.id=rm.social_target_id)),rm.date_collecte) <= :date_to';
            $params['date_to'] = $to;
        }

        if ($requireDate) {
            $clauses[] = 'rm.date_collecte IS NOT NULL';
        }

        if (empty($clauses)) {
            return '';
        }

        return 'WHERE ' . implode(' AND ', $clauses);
    }

    public function getIndividualReportRows(array $filters) {
        return $this->getEnrichedMetrics($filters, 5000);
    }

    public function getPublicationAggregateReport(array $filters) {
        $params = [];
        $where = $this->buildFiltersWhere($filters, $params);

        $sql = 'SELECT
                COALESCE(rm.contenu_id, 0) AS contenu_id,
                COALESCE(ct.sujet, sp.master_title, "Publication non rattachee") AS publication,
                COUNT(*) AS collectes,
                SUM(rm.impressions) AS impressions,
                SUM(COALESCE(rm.couverture, 0)) AS couverture,
                SUM(rm.vues) AS vues,
                SUM(rm.likes) AS likes,
                SUM(rm.commentaires) AS commentaires,
                SUM(rm.partages) AS partages,
                SUM(rm.clics) AS clics,
                AVG(rm.ctr) AS ctr_moyen,
                AVG(rm.engagement_rate) AS engagement_rate_moyen
            FROM reporting_metrics rm
            LEFT JOIN contenus ct ON ct.id = rm.contenu_id
            LEFT JOIN social_publications sp ON sp.id = rm.social_publication_id
            ' . $where . '
            GROUP BY rm.contenu_id, rm.social_publication_id, ct.sujet, sp.master_title
            ORDER BY vues DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMonthlyAggregateReport(array $filters) {
        $params = [];
        $where = $this->buildFiltersWhere($filters, $params, true);

        $sql = 'SELECT
                DATE_FORMAT(COALESCE(DATE((SELECT mpt.published_at FROM social_publication_targets mpt WHERE mpt.id=rm.social_target_id)),rm.date_collecte), "%Y-%m") AS mois,
                rm.plateforme,
                COALESCE(ct.sujet, sp.master_title, "Publication non rattachee") AS publication,
                COUNT(*) AS collectes,
                SUM(rm.impressions) AS impressions_total,
                AVG(rm.impressions) AS impressions_moyenne,
                SUM(rm.couverture) AS couverture_total,
                AVG(rm.couverture) AS couverture_moyenne,
                SUM(rm.vues) AS vues_total,
                AVG(rm.vues) AS vues_moyenne,
                SUM(rm.clics) AS clics_total,
                AVG(rm.clics) AS clics_moyenne,
                AVG(rm.ctr) AS ctr_moyen,
                AVG(rm.engagement_rate) AS engagement_rate_moyen
            FROM reporting_metrics rm
            LEFT JOIN contenus ct ON ct.id = rm.contenu_id
            LEFT JOIN social_publications sp ON sp.id = rm.social_publication_id
            ' . $where . '
            GROUP BY mois, rm.plateforme, rm.social_publication_id, ct.sujet, sp.master_title
            ORDER BY mois DESC, plateforme ASC, publication ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function pearsonCorrelation(array $xValues, array $yValues) {
        $n = min(count($xValues), count($yValues));
        if ($n < 2) {
            return 0.0;
        }

        $sumX = 0.0;
        $sumY = 0.0;
        $sumXY = 0.0;
        $sumX2 = 0.0;
        $sumY2 = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $x = (float) $xValues[$i];
            $y = (float) $yValues[$i];
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
            $sumY2 += $y * $y;
        }

        $numerator = ($n * $sumXY) - ($sumX * $sumY);
        $denominator = sqrt((($n * $sumX2) - ($sumX * $sumX)) * (($n * $sumY2) - ($sumY * $sumY)));

        if ($denominator <= 0) {
            return 0.0;
        }

        return round($numerator / $denominator, 4);
    }

    private function enrichMetricRows(array $rows) {
        if (empty($rows)) {
            return [];
        }

        $networkConfig = $this->getNetworkKpiConfig();
        $groups = [];
        foreach ($rows as $index => $row) {
            $publicationId = (string) ($row['publication_id'] ?? 'manual-' . (string) ($row['id'] ?? $index));
            $network = strtolower((string) ($row['plateforme'] ?? ''));
            $groups[$publicationId . '|' . $network][] = $index;
        }

        foreach ($groups as $groupIndexes) {
            usort($groupIndexes, function ($leftIndex, $rightIndex) use ($rows) {
                $leftDate = (string) ($rows[$leftIndex]['date_collecte'] ?? '');
                $rightDate = (string) ($rows[$rightIndex]['date_collecte'] ?? '');
                if ($leftDate === $rightDate) {
                    return ((int) ($rows[$leftIndex]['id'] ?? 0)) <=> ((int) ($rows[$rightIndex]['id'] ?? 0));
                }
                return strcmp($leftDate, $rightDate);
            });

            $prevScore = null;
            $prevDate = null;
            $prevKpis = [];
            foreach ($groupIndexes as $rowIndex) {
                $row = $rows[$rowIndex];
                $network = strtolower((string) ($row['plateforme'] ?? ''));
                $networkMeta = is_array($networkConfig[$network] ?? null) ? $networkConfig[$network] : [];
                $kpiPayload = $this->parseKpiPayload((string) ($row['kpi_payload'] ?? '{}'));
                $score = $this->calculateScore($kpiPayload, $networkMeta);

                $intervalDays = 0;
                if ($prevDate !== null && !empty($row['date_collecte'])) {
                    try {
                        $currentDate = new DateTime((string) $row['date_collecte']);
                        $previousDate = new DateTime((string) $prevDate);
                        $intervalDays = max(1, (int) $previousDate->diff($currentDate)->days);
                    } catch (Throwable $exception) {
                        $intervalDays = 0;
                    }
                }

                $growthRate = 0.0;
                $dailyRate = 0.0;
                if ($prevScore !== null) {
                    $deltaScore = $score - $prevScore;
                    $growthRate = $prevScore > 0 ? ($deltaScore / $prevScore) * 100 : 0.0;
                    $dailyRate = $intervalDays > 0 ? ($deltaScore / $intervalDays) : $deltaScore;
                }

                $kpiGrowthMap = [];
                $kpiDailyMap = [];
                foreach ($kpiPayload as $kpiName => $kpiValue) {
                    $previousKpiValue = isset($prevKpis[$kpiName]) ? (float) $prevKpis[$kpiName] : null;
                    if ($previousKpiValue === null) {
                        $kpiGrowthMap[$kpiName] = 0.0;
                        $kpiDailyMap[$kpiName] = 0.0;
                        continue;
                    }

                    $delta = (float) $kpiValue - $previousKpiValue;
                    $kpiGrowthMap[$kpiName] = $previousKpiValue > 0 ? ($delta / $previousKpiValue) * 100 : 0.0;
                    $kpiDailyMap[$kpiName] = $intervalDays > 0 ? ($delta / $intervalDays) : $delta;
                }

                $rows[$rowIndex]['score_global'] = round($score, 2);
                $rows[$rowIndex]['growth_rate'] = round($growthRate, 4);
                $rows[$rowIndex]['daily_rate'] = round($dailyRate, 4);
                $rows[$rowIndex]['interval_days'] = $intervalDays;
                $rows[$rowIndex]['kpi_growth_map'] = $kpiGrowthMap;
                $rows[$rowIndex]['kpi_daily_map'] = $kpiDailyMap;
                $rows[$rowIndex]['insights'] = $this->buildRowInsights($score, $growthRate, $dailyRate);

                $prevScore = $score;
                $prevDate = (string) ($row['date_collecte'] ?? '');
                $prevKpis = $kpiPayload;
            }
        }

        usort($rows, static function ($left, $right) {
            $leftDate = (string) ($left['date_collecte'] ?? '');
            $rightDate = (string) ($right['date_collecte'] ?? '');
            if ($leftDate === $rightDate) {
                return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
            }
            return strcmp($rightDate, $leftDate);
        });

        return $rows;
    }

    private function parseKpiPayload($rawPayload) {
        $decoded = json_decode((string) $rawPayload, true);
        if (!is_array($decoded)) {
            return [];
        }

        $payload = [];
        foreach ($decoded as $kpiName => $value) {
            $key = strtolower(trim((string) $kpiName));
            if ($key === '' || !is_numeric($value)) {
                continue;
            }
            $payload[$key] = (float) $value;
        }
        return $payload;
    }

    private function calculateScore(array $kpiPayload, array $networkMeta) {
        if (empty($kpiPayload)) {
            return 0.0;
        }

        $weights = $this->resolveWeights($kpiPayload, $networkMeta);
        if (empty($weights)) {
            return 0.0;
        }

        $score = 0.0;
        foreach ($weights as $kpiName => $weight) {
            $value = (float) ($kpiPayload[$kpiName] ?? 0.0);
            $normalized = $this->normalizeKpiValue($kpiName, $value);
            $score += $normalized * (float) $weight;
        }

        return max(0.0, min(100.0, $score));
    }

    private function resolveWeights(array $kpiPayload, array $networkMeta) {
        $weights = [];
        $configWeights = is_array($networkMeta['weights'] ?? null) ? $networkMeta['weights'] : [];
        foreach ($configWeights as $kpiName => $weight) {
            $key = strtolower(trim((string) $kpiName));
            if ($key === '' || !array_key_exists($key, $kpiPayload)) {
                continue;
            }
            $numericWeight = (float) $weight;
            if ($numericWeight <= 0) {
                continue;
            }
            $weights[$key] = $numericWeight;
        }

        if (empty($weights)) {
            $priority = ['engagement_rate', 'ctr', 'reach', 'impressions', 'video_views', 'vues', 'clicks', 'clics', 'likes', 'comments', 'commentaires', 'shares', 'partages'];
            foreach ($priority as $kpiName) {
                if (array_key_exists($kpiName, $kpiPayload)) {
                    $weights[$kpiName] = 1.0;
                }
                if (count($weights) >= 3) {
                    break;
                }
            }
        }

        if (empty($weights)) {
            foreach ($kpiPayload as $kpiName => $_) {
                $weights[$kpiName] = 1.0;
            }
        }

        $sum = array_sum($weights);
        if ($sum <= 0) {
            return [];
        }

        foreach ($weights as $kpiName => $weight) {
            $weights[$kpiName] = (float) $weight / $sum;
        }

        return $weights;
    }

    private function normalizeKpiValue($kpiName, $value) {
        $kpiName = strtolower((string) $kpiName);
        $value = max(0.0, (float) $value);

        if (strpos($kpiName, 'rate') !== false || strpos($kpiName, 'ctr') !== false || strpos($kpiName, 'score') !== false) {
            if ($value <= 1.0) {
                $value *= 100.0;
            }
            return min(100.0, $value);
        }

        if (strpos($kpiName, 'watch_time') !== false) {
            return min(100.0, $value * 5.0);
        }

        $logScaled = log10($value + 1.0);
        return min(100.0, ($logScaled / 6.0) * 100.0);
    }

    private function buildRowInsights($score, $growthRate, $dailyRate) {
        $insights = [];

        if ($score >= 75) {
            $insights[] = 'Publication performante';
        } elseif ($score < 40) {
            $insights[] = 'Performance faible';
        }

        if ($growthRate < 2) {
            $insights[] = 'Faible progression';
        }

        if ($dailyRate > 2) {
            $insights[] = 'Progression quotidienne positive';
        }

        return array_values(array_unique($insights));
    }

    private function resolveNetworkLabel($network) {
        $network = strtolower(trim((string) $network));
        $config = $this->getNetworkKpiConfig();
        if (is_array($config[$network] ?? null)) {
            return (string) ($config[$network]['label'] ?? ucfirst($network));
        }
        return ucfirst($network);
    }
    private function assertCampaignAnalyticsAccess(int $campaignId,bool $write=false): void {
        $stmt=$this->db->prepare('SELECT c.*,cl.tenant_id,cl.organization_id FROM campagnes c JOIN clients cl ON cl.id=c.client_id WHERE c.id=:id LIMIT 1');
        $stmt->execute(['id'=>$campaignId]);
        AgencyAccessPolicy::assertRecordCapability('campagnes',$stmt->fetch(PDO::FETCH_ASSOC)?:null,'analytics',$write);
    }

    private function assertMetricAnalyticsAccess(int $metricId,bool $write=false): void {
        $stmt=$this->db->prepare('SELECT rm.*,COALESCE(cp.client_id,pr.client_id) client_id,cl.tenant_id,cl.organization_id FROM reporting_metrics rm LEFT JOIN campagnes cp ON cp.id=rm.campagne_id LEFT JOIN projets pr ON pr.id=rm.project_id JOIN clients cl ON cl.id=COALESCE(cp.client_id,pr.client_id) WHERE rm.id=:id LIMIT 1');
        $stmt->execute(['id'=>$metricId]);
        AgencyAccessPolicy::assertRecordCapability('reporting_metrics',$stmt->fetch(PDO::FETCH_ASSOC)?:null,'analytics',$write);
    }
}
