<?php
class SocialMetricsCollectorService {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function collectTarget(int $targetId, int $tenantId, ?int $userId): array {
        $stmt = $this->db->prepare('SELECT t.*, p.tenant_id, p.project_id, p.content_id, p.master_title,
                c.access_token_encrypted, c.status connection_status
            FROM social_publication_targets t
            JOIN social_publications p ON p.id=t.publication_id
            JOIN social_connections c ON c.id=t.connection_id
            WHERE t.id=:target AND p.tenant_id=:tenant AND t.status="Published"
              AND t.remote_deleted_at IS NULL LIMIT 1');
        $stmt->execute(['target' => $targetId, 'tenant' => $tenantId]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$target) throw new RuntimeException('Publication publiée introuvable.');
        if ($target['connection_status'] !== 'Connected') throw new RuntimeException('Reconnectez le compte social avant la collecte.');
        if (empty($target['external_post_id'])) throw new RuntimeException('Identifiant distant absent.');

        $token = CryptoService::decrypt((string) $target['access_token_encrypted']);
        if ($token === '') throw new RuntimeException('Jeton social absent ou illisible.');

        $metrics = match ((string) $target['provider']) {
            'facebook' => $this->collectFacebook($target, $token),
            'instagram' => $this->collectInstagram($target, $token),
            default => throw new RuntimeException('Collecte automatique non activée pour ce réseau.'),
        };

        $context = $this->resolveContext($target);
        $payload = json_encode($metrics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $interactions = (int)($metrics['likes'] ?? 0) + (int)($metrics['commentaires'] ?? 0)
            + (int)($metrics['partages'] ?? 0) + (int)($metrics['sauvegardes'] ?? 0);
        $impressions = max(0, (int)($metrics['impressions'] ?? 0));
        $engagement = $impressions > 0 ? round(($interactions / $impressions) * 100, 4) : null;

        $insert = $this->db->prepare('INSERT INTO reporting_metrics
            (tenant_id,campagne_id,project_id,contenu_id,social_publication_id,social_target_id,source,
             plateforme,date_collecte,collected_at,impressions,couverture,vues,likes,commentaires,partages,
             clics,ctr,engagement_rate,avg_watch_time,sauvegardes,abonnes_gagnes,kpi_payload,url_publication)
            VALUES (:tenant,:campaign,:project,:content,:publication,:target,"api",:provider,CURDATE(),NOW(),
             :impressions,:reach,:views,:likes,:comments,:shares,:clicks,:ctr,:engagement,:watch,:saves,:followers,
             :payload,:url)');
        $insert->execute([
            'tenant' => $tenantId,
            'campaign' => $context['campaign_id'],
            'project' => $context['project_id'],
            'content' => $context['content_id'],
            'publication' => (int)$target['publication_id'],
            'target' => $targetId,
            'provider' => (string)$target['provider'],
            'impressions' => $impressions,
            'reach' => max(0, (int)($metrics['couverture'] ?? 0)),
            'views' => max(0, (int)($metrics['vues'] ?? 0)),
            'likes' => max(0, (int)($metrics['likes'] ?? 0)),
            'comments' => max(0, (int)($metrics['commentaires'] ?? 0)),
            'shares' => max(0, (int)($metrics['partages'] ?? 0)),
            'clicks' => max(0, (int)($metrics['clics'] ?? 0)),
            'ctr' => $metrics['ctr'] ?? null,
            'engagement' => $metrics['engagement_rate'] ?? $engagement,
            'watch' => $metrics['avg_watch_time'] ?? null,
            'saves' => max(0, (int)($metrics['sauvegardes'] ?? 0)),
            'followers' => max(0, (int)($metrics['abonnes_gagnes'] ?? 0)),
            'payload' => $payload,
            'url' => trim((string)($target['external_post_url'] ?? '')) ?: null,
        ]);
        $metricId = (int)$this->db->lastInsertId();

        $event = $this->db->prepare('INSERT INTO social_publication_events
            (tenant_id,publication_id,target_id,event_type,status,message,payload_json,created_by)
            VALUES (:tenant,:publication,:target,"metrics_collected","Success",:message,:payload,:user)');
        $event->execute([
            'tenant' => $tenantId, 'publication' => (int)$target['publication_id'], 'target' => $targetId,
            'message' => 'KPI collectés depuis '.ucfirst((string)$target['provider']).'.',
            'payload' => $payload, 'user' => $userId,
        ]);

        return ['metric_id' => $metricId, 'metrics' => $metrics];
    }

    public function collectPublished(int $tenantId, ?int $userId, int $limit = 25): array {
        $limit = max(1, min(100, $limit));
        $stmt = $this->db->prepare('SELECT t.id FROM social_publication_targets t
            JOIN social_publications p ON p.id=t.publication_id
            WHERE p.tenant_id=:tenant AND t.status="Published" AND t.remote_deleted_at IS NULL
              AND t.provider IN ("facebook","instagram")
            ORDER BY COALESCE(t.published_at,t.updated_at) DESC LIMIT '.$limit);
        $stmt->execute(['tenant' => $tenantId]);
        $result = ['collected' => 0, 'failed' => 0, 'errors' => []];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $targetId) {
            try { $this->collectTarget((int)$targetId, $tenantId, $userId); $result['collected']++; }
            catch (Throwable $exception) { $result['failed']++; $result['errors'][] = $exception->getMessage(); }
        }
        return $result;
    }

    private function resolveContext(array $target): array {
        $contentId = (int)($target['content_id'] ?? 0) ?: null;
        $projectId = (int)($target['project_id'] ?? 0) ?: null;
        $campaignId = null;
        if ($contentId) {
            $stmt = $this->db->prepare('SELECT campagne_id,projet_id FROM contenus WHERE id=:id LIMIT 1');
            $stmt->execute(['id' => $contentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $campaignId = (int)($row['campagne_id'] ?? 0) ?: null;
            $projectId = (int)($row['projet_id'] ?? 0) ?: $projectId;
        }
        if (!$campaignId && $projectId) {
            $stmt = $this->db->prepare('SELECT campagne_id FROM projets WHERE id=:id LIMIT 1');
            $stmt->execute(['id' => $projectId]);
            $campaignId = (int)$stmt->fetchColumn() ?: null;
        }
        return ['campaign_id' => $campaignId, 'project_id' => $projectId, 'content_id' => $contentId];
    }

    private function collectFacebook(array $target, string $token): array {
        $data = $this->graph('/'.rawurlencode((string)$target['external_post_id']), [
            'fields' => 'shares,comments.limit(0).summary(true),reactions.limit(0).summary(true)',
            'access_token' => $token,
        ]);
        $metrics = [
            'likes' => (int)($data['reactions']['summary']['total_count'] ?? 0),
            'commentaires' => (int)($data['comments']['summary']['total_count'] ?? 0),
            'partages' => (int)($data['shares']['count'] ?? 0),
        ];
        try {
            $insights = $this->graph('/'.rawurlencode((string)$target['external_post_id']).'/insights', [
                'metric' => 'post_impressions,post_impressions_unique,post_clicks', 'access_token' => $token,
            ]);
            foreach ((array)($insights['data'] ?? []) as $item) {
                $value = (int)($item['values'][0]['value'] ?? 0);
                if (($item['name'] ?? '') === 'post_impressions') $metrics['impressions'] = $value;
                if (($item['name'] ?? '') === 'post_impressions_unique') $metrics['couverture'] = $value;
                if (($item['name'] ?? '') === 'post_clicks') $metrics['clics'] = $value;
            }
        } catch (Throwable $ignored) {}
        return $metrics;
    }

    private function collectInstagram(array $target, string $token): array {
        $data = $this->graph('/'.rawurlencode((string)$target['external_post_id']), [
            'fields' => 'like_count,comments_count,media_type', 'access_token' => $token,
        ]);
        $metrics = ['likes' => (int)($data['like_count'] ?? 0), 'commentaires' => (int)($data['comments_count'] ?? 0)];
        try {
            $insights = $this->graph('/'.rawurlencode((string)$target['external_post_id']).'/insights', [
                'metric' => 'reach,views,saved,shares', 'access_token' => $token,
            ]);
            foreach ((array)($insights['data'] ?? []) as $item) {
                $value = (int)($item['values'][0]['value'] ?? $item['total_value']['value'] ?? 0);
                $map = ['reach' => 'couverture', 'views' => 'vues', 'saved' => 'sauvegardes', 'shares' => 'partages'];
                if (isset($map[$item['name'] ?? ''])) $metrics[$map[$item['name']]] = $value;
            }
        } catch (Throwable $ignored) {}
        return $metrics;
    }

    private function graph(string $path, array $params): array {
        $token = (string)($params['access_token'] ?? '');
        $secret = trim((string)config_env_value('META_CLIENT_SECRET', ''));
        if ($token !== '' && $secret !== '') $params['appsecret_proof'] = hash_hmac('sha256', $token, $secret);
        $version = trim((string)config_env_value('META_GRAPH_VERSION', 'v23.0'));
        if (!preg_match('/^v\d+\.\d+$/', $version)) $version = 'v23.0';
        $url = 'https://graph.facebook.com/'.$version.$path.'?'.http_build_query($params);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 45, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
        $body = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); curl_close($ch);
        $data = json_decode((string)$body, true);
        if ($body === false || $status < 200 || $status >= 300) {
            $meta = is_array($data['error'] ?? null) ? $data['error'] : [];
            throw new RuntimeException((string)($meta['message'] ?? $error ?: ('Meta HTTP '.$status)));
        }
        return is_array($data) ? $data : [];
    }
}
