<?php
class AssetController extends Controller {
    public function download() {
        $relativePath = trim((string) ($_GET['path'] ?? ''), '/');
        $downloadName = trim((string) ($_GET['name'] ?? 'fichier'));

        if ($relativePath === '' || strpos($relativePath, '..') !== false) {
            http_response_code(400);
            echo 'Fichier invalide.';
            return;
        }

        $absolutePath = UPLOADS_PATH . '/' . $relativePath;
        if (!is_file($absolutePath)) {
            http_response_code(404);
            echo 'Fichier introuvable.';
            return;
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $baseName = preg_replace('/[^A-Za-z0-9 _-]+/', '-', $downloadName);
        $baseName = trim((string) $baseName);
        if ($baseName === '') {
            $baseName = 'fichier';
        }

        $finalName = $baseName . ($extension !== '' ? ('.' . $extension) : '');
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $finalName) . '"');
        header('Content-Length: ' . filesize($absolutePath));
        readfile($absolutePath);
        exit;
    }
}
