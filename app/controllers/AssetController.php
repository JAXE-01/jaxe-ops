<?php
class AssetController extends Controller {
    public function download() {
        $relativePath = str_replace('\\', '/', trim((string) ($_GET['path'] ?? ''), '/'));
        $downloadName = trim((string) ($_GET['name'] ?? basename($relativePath))) ?: 'fichier';

        if ($relativePath === '' || preg_match('#(^|/)\.\.(/|$)#', $relativePath) || strpos($relativePath, "\0") !== false) {
            http_response_code(400);
            echo 'Fichier invalide.';
            return;
        }

        $uploadsRoot = realpath((string) UPLOADS_PATH);
        $absolutePath = realpath((string) UPLOADS_PATH . '/' . $relativePath);
        if ($uploadsRoot === false || $absolutePath === false || !is_file($absolutePath)) {
            http_response_code(404);
            echo 'Fichier introuvable.';
            return;
        }

        $rootPrefix = rtrim(str_replace('\\', '/', $uploadsRoot), '/') . '/';
        $normalizedPath = str_replace('\\', '/', $absolutePath);
        if (strpos($normalizedPath, $rootPrefix) !== 0) {
            http_response_code(403);
            echo 'Accès refusé.';
            return;
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $mime = $this->detectMimeType($absolutePath, $extension);
        $inlineExtensions = ['jpg','jpeg','png','webp','gif','svg','avif','mp4','webm','pdf'];
        $disposition = in_array($extension, $inlineExtensions, true) ? 'inline' : 'attachment';
        $safeName = preg_replace('/[\r\n"\\]+/', '-', $downloadName) ?: 'fichier';

        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"; filename*=UTF-8\'\'' . rawurlencode($safeName));
        header('Content-Length: ' . filesize($absolutePath));
        header('Cache-Control: private, max-age=3600');
        readfile($absolutePath);
        exit;
    }

    private function detectMimeType(string $path, string $extension): string {
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->file($path);
            if (is_string($detected) && $detected !== '') {
                return $detected;
            }
        }
        $fallbacks = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','gif'=>'image/gif','svg'=>'image/svg+xml','avif'=>'image/avif','mp4'=>'video/mp4','webm'=>'video/webm','pdf'=>'application/pdf'];
        return $fallbacks[$extension] ?? 'application/octet-stream';
    }
}
