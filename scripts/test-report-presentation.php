<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/../app/helpers/ReportPresentation.php';
require_once __DIR__.'/../app/helpers/ReportTablePdf.php';
function checkReport($condition,$message){if(!$condition)throw new RuntimeException($message);}
checkReport(ReportPresentation::url('javascript:alert(1)')==='', 'Reject unsafe links');
checkReport(ReportPresentation::url('https://www.facebook.com/123/posts/456')!=='','Keep Facebook links');
checkReport(ReportPresentation::value(['vues'=>null],'vues')==='—','Missing is not zero');
checkReport(ReportPresentation::value(['vues'=>0],'vues')==='0','Keep real zero');
checkReport(ReportPresentation::columns('individual',['columns'=>['individual'=>['id','vues','bad']]])===['vues'],'Column allow-list');
checkReport(!str_contains(ReportPresentation::sortSql('individual',['sort'=>'id; DROP TABLE x','direction'=>'bad']),'DROP'),'Sort allow-list');
checkReport(ReportPresentation::type('video_inline')==='video','Normalize video');
checkReport(ReportPresentation::type('')==='unknown','Do not invent format');
checkReport(ReportPresentation::tables(['tables'=>['bad','monthly']])===['monthly'],'Table allow-list');
checkReport(ReportPresentation::tables(['tables'=>['']])===[],'Empty selection stays empty');
$sorted=ReportPresentation::sortRows([['vues'=>null],['vues'=>2],['vues'=>16]],'individual',['sort'=>'vues','direction'=>'desc']);
checkReport(array_column($sorted,'vues')===[16,2,null],'Export sort with missing values last');
checkReport(str_contains(ReportPresentation::cell(['url_publication'=>'https://facebook.com/123'],'url_publication'),'href="https://facebook.com/123"'),'PDF links use anchors');
if(in_array('--render',$argv,true)) {
    require_once __DIR__.'/../app/third_party/tcpdf/tcpdf.php';
    $dir=__DIR__.'/../tmp/pdfs'; if(!is_dir($dir))mkdir($dir,0777,true);
    foreach(['individual','publication','monthly'] as $model) {
        $rows=[];
        for($i=0;$i<35;$i++) $rows[]=['date_publication'=>'2026-08-26','mois'=>'2026-08','page_nom'=>'ELVEC TOGO','publication_titre'=>'Import Facebook · 26/08/2026','publication'=>'Import Facebook · 26/08/2026','publication_caption'=>'Un chantier réussi commence par le bon choix d’équipement.','content_type'=>'image','url_publication'=>'https://www.facebook.com/123/posts/456','vues'=>16,'likes'=>3,'commentaires'=>0,'partages'=>0,'clics'=>0,'impressions'=>null,'plateforme'=>'facebook','vues_total'=>16,'likes_total'=>3,'commentaires_total'=>0,'partages_total'=>0,'clics_total'=>0,'publications'=>1];
        $html=ReportTablePdf::html('Rapport '.$model.' — ELVEC',$rows,ReportPresentation::columns($model,[]));
        $pdf=new TCPDF('L','mm','A4',true,'UTF-8',false);
        $pdf->setPrintHeader(false);$pdf->setPrintFooter(false);$pdf->SetMargins(12,12,12);$pdf->SetAutoPageBreak(true,14);$pdf->SetFont('dejavusans','',9);$pdf->AddPage();$pdf->writeHTML($html);
        file_put_contents($dir.'/report-'.$model.'.pdf',$pdf->Output('report.pdf','S'));
    }
}
if(in_array('--selection',$argv,true)) {
    require_once __DIR__.'/../app/third_party/tcpdf/tcpdf.php';
    require_once __DIR__.'/../app/helpers/PdfExportService.php';
    ob_start(static function($bytes){file_put_contents(__DIR__.'/../tmp/pdfs/report-selection.pdf',$bytes);return '';});
    $tables=[];
    foreach(ReportPresentation::models() as $model=>$title) $tables[]=['title'=>$title.' — ELVEC','rows'=>[['page_nom'=>'ELVEC TOGO','date_publication'=>'2026-08-26','publication'=>'Test','publication_titre'=>'Test','vues'=>16,'url_publication'=>'https://www.facebook.com/123/posts/456']],'columns'=>ReportPresentation::columns($model,[])];
    PdfExportService::outputSelectedTablesPdf($tables,'selection.pdf');exit;
}
echo "Report presentation checks passed\n";
