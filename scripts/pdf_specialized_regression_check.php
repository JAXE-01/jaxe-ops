<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/app/helpers/PdfExportService.php';

$rows = [
    ['periode_mois'=>'août 2026','date_prevue'=>'2026-08-03','client'=>'Jaxe Communication','projet'=>'Campagne rentrée','titre'=>'Conseil du lundi','type_livrable'=>'Visuel','reseau'=>'Facebook / Instagram','sujet'=>'Préparer sa communication','message'=>'Trois actions simples pour gagner du temps.','plan_script'=>'Accroche, démonstration, appel à l’action','texte_script'=>'Bonjour à toutes et à tous. Voici trois actions concrètes…','script_contenu'=>'Ton clair, premium et orienté résultat.'],
    ['periode_mois'=>'août 2026','date_prevue'=>'2026-08-07','client'=>'Togo Assistance Services','projet'=>'Abonnement éditorial','titre'=>'Démonstration produit','type_livrable'=>'Vidéo','reseau'=>'Instagram / TikTok','sujet'=>'Réduire le temps de traitement','message'=>'Une démonstration utile et rassurante.','plan_script'=>'Problème > preuve > solution > CTA','texte_script'=>'Votre équipe perd-elle du temps sur cette tâche ?','script_contenu'=>'Prévoir plans rapprochés et sous-titres.'],
];
$outDir = dirname(__DIR__).'/storage/qa-pdf';
if (!is_dir($outDir)) mkdir($outDir, 0775, true);

foreach ([['buildCalendarHtml','Calendrier éditorial',$outDir.'/calendar.pdf','landscape'],['buildBriefsHtml','Briefs et scripts',$outDir.'/briefs.pdf','portrait']] as [$method,$title,$path,$orientation]) {
    $reflection = new ReflectionMethod(PdfExportService::class, $method);
    $reflection->setAccessible(true);
    $html = $reflection->invoke(null, $title, $rows);
    $pdf = new Dompdf\Dompdf();
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->setPaper('A4', $orientation);
    $pdf->render();
    $bytes = $pdf->output();
    if (!str_starts_with($bytes, '%PDF-') || strlen($bytes) < 2000) throw new RuntimeException('PDF invalide: '.$path);
    file_put_contents($path, $bytes);
    echo $path."\n";
}
