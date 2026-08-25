<?php
class FileTrashService {
    public static function trashByRelativePath($relativePath, array $context = []) {
        $relativePath = trim((string) $relativePath, '/\\');
        if ($relativePath === '' || strpos($relativePath, '.trash/') === 0) {
            return false;
        }

        $absoluteOriginalPath = UPLOADS_PATH . '/' . $relativePath;
        if (!is_file($absoluteOriginalPath)) {
            return false;
        }

        $trashDirectory = UPLOADS_PATH . '/.trash/' . date('Y') . '/' . date('m') . '/' . date('d');
        if (!is_dir($trashDirectory) && !mkdir($trashDirectory, 0777, true) && !is_dir($trashDirectory)) {
            return false;
        }

        $originalName = basename($absoluteOriginalPath);
        $trashFileName = uniqid('trash-', true) . '-' . $originalName;
        $absoluteTrashPath = $trashDirectory . '/' . $trashFileName;

        if (!@rename($absoluteOriginalPath, $absoluteTrashPath)) {
            return false;
        }

        $trashRelativePath = '.trash/' . date('Y') . '/' . date('m') . '/' . date('d') . '/' . $trashFileName;

        self::recordTrashItem([
            'original_path' => $relativePath,
            'trash_path' => $trashRelativePath,
            'original_name' => (string) ($context['original_name'] ?? $originalName),
            'size_bytes' => (int) ($context['size_bytes'] ?? (@filesize($absoluteTrashPath) ?: 0)),
            'module_key' => (string) ($context['module_key'] ?? ''),
            'source_table' => (string) ($context['source_table'] ?? ''),
            'source_record_id' => (int) ($context['source_record_id'] ?? 0),
            'deleted_by' => (int) ($context['deleted_by'] ?? 0),
        ]);

        return true;
    }

    public static function listTrashItems($limit = 500) {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('SELECT * FROM upload_trash_items WHERE status = :status ORDER BY deleted_at DESC LIMIT ' . max(1, (int) $limit));
            $stmt->execute(['status' => 'trashed']);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            return [];
        }
    }

    public static function purgeTrashItem($id) {
        $id = (int) $id;
        if ($id <= 0) {
            return false;
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('SELECT * FROM upload_trash_items WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            return false;
        }
        if (!$item || (string) ($item['status'] ?? '') !== 'trashed') {
            return false;
        }

        $trashRelativePath = trim((string) ($item['trash_path'] ?? ''), '/\\');
        if ($trashRelativePath !== '') {
            $absoluteTrashPath = UPLOADS_PATH . '/' . $trashRelativePath;
            if (is_file($absoluteTrashPath)) {
                @unlink($absoluteTrashPath);
            }
        }

        try {
            $update = $pdo->prepare('UPDATE upload_trash_items SET status = :status, purged_at = NOW() WHERE id = :id');
            $update->execute([
                'status' => 'purged',
                'id' => $id,
            ]);
        } catch (Throwable $exception) {
            return false;
        }

        return true;
    }

    private static function recordTrashItem(array $payload) {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('INSERT INTO upload_trash_items (
                original_path,
                trash_path,
                original_name,
                size_bytes,
                module_key,
                source_table,
                source_record_id,
                deleted_by,
                deleted_at,
                status
            ) VALUES (
                :original_path,
                :trash_path,
                :original_name,
                :size_bytes,
                :module_key,
                :source_table,
                :source_record_id,
                :deleted_by,
                NOW(),
                :status
            )');

            $stmt->execute([
                'original_path' => (string) ($payload['original_path'] ?? ''),
                'trash_path' => (string) ($payload['trash_path'] ?? ''),
                'original_name' => (string) ($payload['original_name'] ?? ''),
                'size_bytes' => (int) ($payload['size_bytes'] ?? 0),
                'module_key' => (string) ($payload['module_key'] ?? ''),
                'source_table' => (string) ($payload['source_table'] ?? ''),
                'source_record_id' => (int) ($payload['source_record_id'] ?? 0),
                'deleted_by' => (int) ($payload['deleted_by'] ?? 0),
                'status' => 'trashed',
            ]);
        } catch (Throwable $exception) {
            // Move to trash should still succeed on filesystem even if DB log is unavailable.
        }
    }
}
