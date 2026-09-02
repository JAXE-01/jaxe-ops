<?php
function route_url(string $path): string { return 'https://example.test/index.php' . $path; }
ob_start();
require dirname(__DIR__) . '/app/views/layouts/developer-footer.php';
$html = ob_get_clean();
$document = new DOMDocument();
@$document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
$xpath = new DOMXPath($document);
$check = static function (bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
};
$check($xpath->query('//footer/details')->length === 1, 'Missing collapsible footer');
$check($xpath->query('//footer//section')->length === 4, 'Missing network group');
$external = $xpath->query('//footer//a[@target="_blank"]');
$check($external->length === 10, 'Unexpected developer link count');
foreach ($external as $link) {
    $check(str_starts_with($link->getAttribute('href'), 'https://'), 'Non-HTTPS resource');
    $check($link->getAttribute('rel') === 'noopener noreferrer', 'Unsafe external link');
    $check(trim($link->textContent) !== '', 'Unlabelled link');
}
$check($xpath->query('//footer/nav/a')->length === 3, 'Missing legal links');
echo "Footer: 4 networks, 10 secure external links, 3 legal links OK\n";
