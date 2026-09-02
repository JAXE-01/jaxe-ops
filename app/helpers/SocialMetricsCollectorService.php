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
        $impressions = array_key_exists('impressions', $metrics) && $metrics['impressions'] !== null ? max(0, (int)$metrics['impressions']) : null;
        $engagement = $impressions > 0 ? round(($interactions / $impressions) * 100, 4) : null;

        // One API snapshot per target and day: repeated manual clicks refresh it instead of inflating reports.
        $cleanup = $this->db->prepare('DELETE FROM reporting_metrics
            WHERE tenant_id=:tenant AND social_target_id=:target AND source="api" AND date_collecte=CURDATE()');
        $cleanup->execute(['tenant' => $tenantId, 'target' => $targetId]);

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
            'reach' => $this->nullableCount($metrics,'couverture'),
            'views' => $this->nullableCount($metrics,'vues'),
            'likes' => $this->nullableCount($metrics,'likes'),
            'comments' => $this->nullableCount($metrics,'commentaires'),
            'shares' => $this->nullableCount($metrics,'partages'),
            'clicks' => $this->nullableCount($metrics,'clics'),
            'ctr' => $metrics['ctr'] ?? null,
            'engagement' => $metrics['engagement_rate'] ?? $engagement,
            'watch' => $metrics['avg_watch_time'] ?? null,
            'saves' => $this->nullableCount($metrics,'sauvegardes'),
            'followers' => $this->nullableCount($metrics,'abonnes_gagnes'),
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

    public function importHistory(int $connectionId, int $tenantId, ?int $userId, string $from, string $to, int $limit = 100): array {
        $fromDate = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
        $toDate = DateTimeImmutable::createFromFormat('!Y-m-d', $to);
        if (!$fromDate || !$toDate || $fromDate > $toDate) throw new RuntimeException('Periode historique invalide.');
        if ($fromDate->diff($toDate)->days > 366) throw new RuntimeException('Importez au maximum 12 mois a la fois.');
        $limit = max(1, min(250, $limit));

        $stmt = $this->db->prepare('SELECT * FROM social_connections
            WHERE id=:id AND tenant_id=:tenant AND status="Connected"
              AND provider IN ("facebook","instagram") LIMIT 1');
        $stmt->execute(['id'=>$connectionId,'tenant'=>$tenantId]);
        $connection = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$connection) throw new RuntimeException('Page Meta connectee introuvable.');
        if (empty($connection['external_account_id'])) throw new RuntimeException('Identifiant Meta de la Page absent.');

        $token = CryptoService::decrypt((string)$connection['access_token_encrypted']);
        if ($token === '') throw new RuntimeException('Jeton Meta absent ou illisible.');
        $provider = (string)$connection['provider'];
        $path = '/'.rawurlencode((string)$connection['external_account_id']).($provider === 'instagram' ? '/media' : '/posts');
        $fields = $provider === 'instagram'
            ? 'id,caption,media_type,timestamp,permalink'
            : 'id,message,created_time,permalink_url,shares,comments.limit(0).summary(true),reactions.limit(0).summary(true)';
        $params = [
            'fields'=>$fields,
            'since'=>$fromDate->format('Y-m-d'),
            'until'=>$toDate->modify('+1 day')->format('Y-m-d'),
            'limit'=>min(100,$limit),
            'access_token'=>$token,
        ];

        $items=[]; $after=null;
        do {
            if ($after !== null) $params['after']=$after; else unset($params['after']);
            $page=$this->graph($path,$params);
            foreach ((array)($page['data']??[]) as $item) {
                if (is_array($item) && !empty($item['id'])) $items[]=$item;
                if (count($items) >= $limit) break 2;
            }
            $after=(string)($page['paging']['cursors']['after']??'');
            if ($after==='') $after=null;
        } while ($after !== null);

        $result=['found'=>count($items),'imported'=>0,'existing'=>0,'collected'=>0,'failed'=>0,'errors'=>[]];
        $find=$this->db->prepare('SELECT t.id FROM social_publication_targets t
            JOIN social_publications p ON p.id=t.publication_id
            WHERE p.tenant_id=:tenant AND t.connection_id=:connection AND t.external_post_id=:external LIMIT 1');
        $insertPublication=$this->db->prepare('INSERT INTO social_publications
            (tenant_id,organization_id,client_id,project_id,content_id,master_title,master_caption,publish_mode,
             scheduled_at,approval_status,approved_by,approved_at,created_by)
            VALUES (:tenant,:organization,:client,NULL,NULL,:title,:caption,"Now",:published,"Approved",:user,:published,:user)');
        $insertTarget=$this->db->prepare('INSERT INTO social_publication_targets
            (publication_id,connection_id,provider,adapted_caption,status,attempts,next_attempt_at,published_at,
             external_post_id,external_post_url)
            VALUES (:publication,:connection,:provider,:caption,"Published",1,:published,:published,:external,:url)');

        foreach ($items as $item) {
            try {
                $external=(string)$item['id'];
                $find->execute(['tenant'=>$tenantId,'connection'=>$connectionId,'external'=>$external]);
                $targetId=(int)$find->fetchColumn();
                if ($targetId) {
                    $result['existing']++;
                } else {
                    $publishedRaw=(string)($item[$provider==='instagram'?'timestamp':'created_time']??'now');
                    $published=date('Y-m-d H:i:s',strtotime($publishedRaw)?:time());
                    $caption=trim((string)($item[$provider==='instagram'?'caption':'message']??''));
                    $title='Import '.ucfirst($provider).' · '.date('d/m/Y',strtotime($published));
                    $this->db->beginTransaction();
                    $insertPublication->execute([
                        'tenant'=>$tenantId,'organization'=>(int)($connection['organization_id']??0)?:null,
                        'client'=>(int)$connection['client_id'],'title'=>$title,'caption'=>$caption,
                        'published'=>$published,'user'=>$userId,
                    ]);
                    $publicationId=(int)$this->db->lastInsertId();
                    $insertTarget->execute([
                        'publication'=>$publicationId,'connection'=>$connectionId,'provider'=>$provider,
                        'caption'=>$caption,'published'=>$published,'external'=>$external,
                        'url'=>trim((string)($item[$provider==='instagram'?'permalink':'permalink_url']??''))?:null,
                    ]);
                    $targetId=(int)$this->db->lastInsertId();
                    $this->db->commit();
                    $result['imported']++;
                }
                $this->collectTarget($targetId,$tenantId,$userId);
                $result['collected']++;
            } catch (Throwable $exception) {
                if ($this->db->inTransaction()) $this->db->rollBack();
                $result['failed']++; $result['errors'][]=$exception->getMessage();
            }
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
            'fields' => 'shares,comments.limit(0).summary(true),reactions.limit(0).summary(true),attachments.limit(1){media_type}',
            'access_token' => $token,
        ]);
        $metrics = [
            'likes' => (int)($data['reactions']['summary']['total_count'] ?? 0),
            'commentaires' => (int)($data['comments']['summary']['total_count'] ?? 0),
            'partages' => (int)($data['shares']['count'] ?? 0),
        ];
        $metrics['_availability'] = [
            'likes'=>['status'=>'available','source'=>'reactions.summary.total_count'],
            'commentaires'=>['status'=>'available','source'=>'comments.summary.total_count'],
            'partages'=>['status'=>'available','source'=>'shares.count'],
        ];
        foreach (['post_impressions'=>'impressions','post_impressions_unique'=>'couverture','post_clicks'=>'clics'] as $meta=>$local) {
            $this->collectInsight($metrics,$target,$token,$meta,$local);
        }
        $this->collectInsight($metrics,$target,$token,'post_media_view','vues');
        if($metrics['vues']===null)$this->collectInsight($metrics,$target,$token,'post_views','vues');
        $mediaType=strtolower((string)($data['attachments']['data'][0]['media_type']??''));
        $metrics['_content_type']=ReportPresentation::type($mediaType);
        if($metrics['vues']===null&&in_array($mediaType,['video','video_inline'],true))$this->collectInsight($metrics,$target,$token,'post_video_views','vues');
        return $metrics;
    }

    private function collectInstagram(array $target, string $token): array {
        $data = $this->graph('/'.rawurlencode((string)$target['external_post_id']), [
            'fields' => 'like_count,comments_count,media_type,media_product_type', 'access_token' => $token,
        ]);
        $metrics = InstagramMetricMapper::fromMedia($data);
        foreach(['reach'=>'couverture','views'=>'vues','saved'=>'sauvegardes','shares'=>'partages'] as$meta=>$local)$this->collectInsight($metrics,$target,$token,$meta,$local);
        foreach(['impressions','clics']as$local){$metrics[$local]=null;$metrics['_availability'][$local]=['status'=>'unavailable','source'=>null,'reason'=>'Non fournie au niveau du média Instagram'];}
        return $metrics;
    }

    private function collectInsight(array &$metrics,array $target,string $token,string $meta,string $local): void {
        try{$response=$this->graph('/'.rawurlencode((string)$target['external_post_id']).'/insights',['metric'=>$meta,'access_token'=>$token]);$item=(array)(($response['data']??[])[0]??[]);$value=$item['values'][0]['value']??$item['total_value']['value']??null;if($value===null||!is_numeric($value))throw new RuntimeException('Aucune valeur retournée');$metrics[$local]=max(0,(int)$value);$metrics['_availability'][$local]=['status'=>'available','source'=>$meta];}
        catch(Throwable $e){$metrics[$local]=null;$metrics['_availability'][$local]=['status'=>'unavailable','source'=>$meta,'reason'=>mb_strimwidth($e->getMessage(),0,500)];}
    }

    private function nullableCount(array $metrics,string $key): ?int {
        return array_key_exists($key,$metrics)&&$metrics[$key]!==null?max(0,(int)$metrics[$key]):null;
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
