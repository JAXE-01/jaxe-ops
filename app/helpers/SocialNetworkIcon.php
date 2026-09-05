<?php
class SocialNetworkIcon {
    public static function render(string $provider): string {
        $paths = [
            'facebook' => '<path d="M14 8h3V4h-3c-3 0-5 2-5 5v3H6v4h3v5h4v-5h3l1-4h-4V9c0-.7.3-1 1-1Z" fill="currentColor"/>',
            'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/>',
            'linkedin' => '<path d="M4 9h4v11H4V9Zm2-5a2 2 0 1 1 0 4 2 2 0 0 1 0-4Zm4 5h4v1.7c.8-1.2 2-2 3.7-2 3.2 0 4.3 2.1 4.3 5.5V20h-4v-5.1c0-1.6-.5-2.7-1.9-2.7-1.6 0-2.1 1.2-2.1 3V20h-4V9Z" fill="currentColor"/>',
            'tiktok' => '<path d="M14 3c.5 2.4 1.9 3.8 4 4.2v3.3a8 8 0 0 1-4-1.2v5.5a6.2 6.2 0 1 1-5.3-6.1v3.4a2.9 2.9 0 1 0 2 2.7V3h3.3Z" fill="currentColor"/>',
            'youtube' => '<path d="M21 8.1a3 3 0 0 0-2.1-2.2C17 5.4 12 5.4 12 5.4s-5 0-6.9.5A3 3 0 0 0 3 8.1a31 31 0 0 0-.5 3.9A31 31 0 0 0 3 16a3 3 0 0 0 2.1 2.1c1.9.5 6.9.5 6.9.5s5 0 6.9-.5A3 3 0 0 0 21 16a31 31 0 0 0 .5-4 31 31 0 0 0-.5-3.9ZM10 15.5v-7l6 3.5-6 3.5Z" fill="currentColor"/>',
        ];
        $key=isset($paths[$provider])?$provider:'generic';
        $path=$paths[$key]??'<circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 12h8" stroke="currentColor" stroke-width="2"/>';
        return '<span class="network-logo network-'.$key.'" aria-label="'.htmlspecialchars(ucfirst($provider),ENT_QUOTES,'UTF-8').'"><svg viewBox="0 0 24 24" aria-hidden="true">'.$path.'</svg></span>';
    }
}
