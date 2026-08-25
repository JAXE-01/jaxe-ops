<?php
class SchemaSynchronizer {
    public static function syncIfNeeded() {
        $pdo = Database::getConnection();
        if (defined('APP_ENV') && strtolower((string) APP_ENV) === 'production') {
            $pending = MigrationRunner::pendingFiles($pdo);
            if (!empty($pending)) { DatabaseBackupService::create($pdo, 'pre-migration'); }
            MigrationRunner::runIfNeeded($pdo);
            return;
        }
        if (!defined('AUTO_SYNC_SCHEMA') || AUTO_SYNC_SCHEMA !== true) {
            return;
        }

        if (!file_exists(INSTALL_SQL_PATH)) {
            return;
        }
        self::ensureTrackingTable($pdo);

        $checksum = sha1_file(INSTALL_SQL_PATH);
        $currentChecksum = self::getCurrentChecksum($pdo);

        if ($currentChecksum === $checksum) {
            self::syncProjectPipelines($pdo);
            MigrationRunner::runIfNeeded($pdo);
            return;
        }

        $sql = trim((string) file_get_contents(INSTALL_SQL_PATH));
        if ($sql === '') {
            return;
        }

        $statements = self::splitStatements($sql);
        foreach ($statements as $statement) {
            $trimmed = trim($statement);
            if ($trimmed === '') {
                continue;
            }
            $pdo->exec($trimmed);
        }

        $query = 'INSERT INTO schema_sync (id, checksum, synced_at) VALUES (1, :checksum, NOW())
            ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), synced_at = VALUES(synced_at)';
        $stmt = $pdo->prepare($query);
        $stmt->execute(['checksum' => $checksum]);

        self::syncProjectPipelines($pdo);
        MigrationRunner::runIfNeeded($pdo);
    }

    private static function ensureTrackingTable(PDO $pdo) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_sync (
            id TINYINT PRIMARY KEY,
            checksum CHAR(40) NOT NULL,
            synced_at DATETIME NOT NULL
        )');
    }

    private static function getCurrentChecksum(PDO $pdo) {
        $stmt = $pdo->query('SELECT checksum FROM schema_sync WHERE id = 1 LIMIT 1');
        $checksum = $stmt->fetchColumn();
        return $checksum ?: null;
    }

    private static function splitStatements($sql) {
        $lines = preg_split('/\R/', $sql);
        $buffer = '';
        $statements = [];

        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            if (strpos($trimmedLine, '--') === 0) {
                continue;
            }

            $buffer .= $line . "\n";
            if (substr(rtrim($line), -1) === ';') {
                $statements[] = $buffer;
                $buffer = '';
            }
        }

        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }

        return $statements;
    }

    private static function syncProjectPipelines(PDO $pdo) {
        $stmt = $pdo->query('SELECT id FROM projets');
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $projectId) {
            PipelineService::syncProject((int) $projectId);
        }
    }
}
