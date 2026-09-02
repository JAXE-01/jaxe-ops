<?php
// Run only against an explicitly supplied disposable MySQL/MariaDB instance.
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
$dsn=getenv('STRAX_SQL_TEST_DSN');
if(!$dsn){fwrite(STDERR,"Set STRAX_SQL_TEST_DSN to an isolated test database.\n");exit(2);}
require __DIR__.'/../app/core/Model.php';
require __DIR__.'/../app/helpers/ReportPresentation.php';
require __DIR__.'/../app/models/ReportingMetricModel.php';
// Query test only: use the same parameterized tenant scope shape without login/bootstrap.
class AgencyAccessPolicy {
    public static function clientSqlScope($alias,$permission,$prefix):array {
        return ['sql'=>$alias.'.tenant_id = :'.$prefix,'params'=>[$prefix=>1]];
    }
}
class SqlTestReportingModel extends ReportingMetricModel {
    public function __construct(PDO $db){$this->db=$db;}
}
$db=new PDO($dsn,getenv('STRAX_SQL_TEST_USER')?:'root',getenv('STRAX_SQL_TEST_PASSWORD')?:'',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$schemas=[
 'clients'=>'id INT,tenant_id INT,entreprise VARCHAR(80)',
 'projets'=>'id INT,client_id INT',
 'campagnes'=>'id INT,client_id INT,nom VARCHAR(80)',
 'contenus'=>'id INT,sujet VARCHAR(80)',
 'social_publications'=>'id INT,tenant_id INT,client_id INT,master_title VARCHAR(80),master_caption TEXT',
 'social_connections'=>'id INT,client_id INT,account_label VARCHAR(80)',
 'social_publication_targets'=>'id INT,publication_id INT,connection_id INT,published_at DATETIME,external_post_url VARCHAR(255)',
 'reporting_metrics'=>'id INT,tenant_id INT,campagne_id INT,project_id INT,contenu_id INT,social_publication_id INT,social_target_id INT,plateforme VARCHAR(20),date_collecte DATE,kpi_payload TEXT,url_publication VARCHAR(255),impressions INT,couverture INT,vues INT,likes INT,commentaires INT,partages INT,clics INT,ctr DECIMAL(8,2),engagement_rate DECIMAL(8,2)'
];
// All fixtures disappear when this PDO connection closes. No application tables are modified.
foreach($schemas as $name=>$columns)$db->exec('CREATE TEMPORARY TABLE '.$name.' ('.$columns.')');
$db->exec("INSERT INTO clients VALUES(1,1,'Client test'); INSERT INTO social_connections VALUES(1,1,'Page test')");
for($i=1;$i<=3;$i++){
    $db->exec("INSERT INTO social_publications VALUES($i,1,1,'Publication $i','Texte test')");
    $date=$i===3?'NULL':"'2026-08-0$i'";
    $views=$i===3?'NULL':($i===1?'0':'16');
    $db->exec("INSERT INTO social_publication_targets VALUES($i,$i,1,$date,'https://www.facebook.com/123/posts/$i')");
    $db->exec("INSERT INTO reporting_metrics (id,tenant_id,social_publication_id,social_target_id,plateforme,date_collecte,kpi_payload,vues,likes,commentaires,partages,clics) VALUES($i,1,$i,$i,'facebook','2026-09-02','{\"_content_type\":\"image\"}',$views,3,0,0,0)");
}
$model=new SqlTestReportingModel($db);$checks=0;
foreach(['individual'=>'getMetrics','publication'=>'getPublicationAggregateReport','monthly'=>'getMonthlyAggregateReport'] as $kind=>$method){
    foreach(array_merge([''],array_keys(ReportPresentation::fields($kind))) as $sort){
        foreach(['asc','desc'] as $direction){
            $result=$model->$method(['sort'=>$sort,'direction'=>$direction]);
            if(!$result)throw new RuntimeException("Empty result: $kind / $sort");
            $checks++;
        }
    }
    $result=$model->$method(['client_id'=>1,'connection_id'=>1,'content_type'=>'image','from'=>'2026-08-01','to'=>'2026-08-31']);
    if(!$result)throw new RuntimeException('Filtered result missing: '.$kind);
}
$rows=$model->getPublicationAggregateReport(['sort'=>'vues','direction'=>'desc']);
if(array_map(static fn($row)=>$row['vues']===null?null:(int)$row['vues'],$rows)!==[16,0,null])throw new RuntimeException('Null/zero/sort regression');
$rows=$model->getPublicationAggregateReport([]);
if(array_column($rows,'social_publication_id')!=[2,1,3])throw new RuntimeException('Default publication date sort regression');
echo "OK: $checks SQL sorts, three models, filters, publication dates, zero and null (".$db->getAttribute(PDO::ATTR_SERVER_VERSION).")\n";
