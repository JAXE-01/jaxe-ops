<?php
class DolibarrService {
    private $config;

    public function __construct(array $config) {
        $this->config = [
            'base_url' => rtrim(trim((string) ($config['base_url'] ?? '')), '/'),
            'api_key' => trim((string) ($config['api_key'] ?? '')),
            'entity' => trim((string) ($config['entity'] ?? ''))
        ];
    }

    public function testConnection() {
        $users = $this->request('users', [
            'limit' => 1,
            'sortfield' => 't.rowid',
            'sortorder' => 'ASC'
        ]);

        return [
            'message' => 'Connexion Dolibarr validee. L API REST repond et la cle est acceptee.',
            'sample_count' => is_array($users) ? count($users) : 0,
            'explorer_url' => $this->getExplorerUrl()
        ];
    }

    public function fetchUsers() {
        return $this->request('users', [
            'limit' => 100,
            'sortfield' => 't.rowid',
            'sortorder' => 'ASC'
        ]);
    }

    public function fetchProjects() {
        return $this->request('projects', [
            'limit' => 100,
            'sortfield' => 't.rowid',
            'sortorder' => 'ASC'
        ]);
    }

    public function getExplorerUrl() {
        return $this->buildUrl('explorer');
    }

    private function request($endpoint, array $query = []) {
        $this->assertConfig();
        $url = $this->buildUrl($endpoint);
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $headers = [
            'Accept: application/json',
            'DOLAPIKEY: ' . $this->config['api_key']
        ];
        if ($this->config['entity'] !== '') {
            $headers[] = 'DOLAPIENTITY: ' . $this->config['entity'];
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                throw new RuntimeException('Connexion Dolibarr impossible: ' . $error);
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => implode("\r\n", $headers),
                    'ignore_errors' => true,
                    'timeout' => 20
                ]
            ]);
            $response = @file_get_contents($url, false, $context);
            $httpCode = 0;
            foreach (($http_response_header ?? []) as $headerLine) {
                if (preg_match('#HTTP/\S+\s+(\d{3})#', $headerLine, $matches)) {
                    $httpCode = (int) $matches[1];
                    break;
                }
            }
            if ($response === false) {
                throw new RuntimeException('Connexion Dolibarr impossible via HTTP stream.');
            }
        }

        $decoded = json_decode((string) $response, true);
        if ($httpCode >= 400) {
            $message = is_array($decoded) && isset($decoded['error']['message']) ? $decoded['error']['message'] : ('Erreur HTTP ' . $httpCode);
            throw new RuntimeException('Dolibarr a refuse la requete: ' . $message);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Reponse Dolibarr invalide ou non JSON.');
        }

        if (isset($decoded['error'])) {
            $message = is_array($decoded['error']) ? ($decoded['error']['message'] ?? 'Erreur API') : (string) $decoded['error'];
            throw new RuntimeException('Erreur API Dolibarr: ' . $message);
        }

        return $decoded;
    }

    private function assertConfig() {
        if ($this->config['base_url'] === '' || $this->config['api_key'] === '') {
            throw new RuntimeException('Renseigne au minimum l URL Dolibarr et la cle API.');
        }
    }

    private function buildUrl($endpoint) {
        $baseUrl = $this->config['base_url'];
        if (strpos($baseUrl, '/api/index.php') === false) {
            $baseUrl .= '/api/index.php';
        }
        return rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
    }
}