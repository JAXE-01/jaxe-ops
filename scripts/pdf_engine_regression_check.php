<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__).'/vendor/autoload.php';
$pdf=new Dompdf\Dompdf();
$pdf->loadHtml('<html><head><meta charset="utf-8"></head><body><h1>Strax — Export de contrôle</h1><table><tr><th>Projet</th><th>Validation</th></tr><tr><td>Campagne été</td><td>Approuvée</td></tr></table></body></html>','UTF-8');
$pdf->setPaper('A4','landscape');$pdf->render();$bytes=$pdf->output();
if(!str_starts_with($bytes,'%PDF-')||strlen($bytes)<1000){fwrite(STDERR,"PDF generation failed\n");exit(1);}echo "OK: moteur PDF, UTF-8, tableau, A4 paysage (test en mémoire).\n";
