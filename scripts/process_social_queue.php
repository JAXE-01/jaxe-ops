<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only\n");}
require dirname(__DIR__).'/config/config.php';
$limit=isset($argv[1])?(int)$argv[1]:20;
$result=(new SocialPublisherService())->processDue($limit);
$result['metrics_collected']=0;
$result['metrics_failed']=0;
$result['metrics_errors']=[];
$collector=new SocialMetricsCollectorService();
foreach((array)($result['published_targets']??[])as$publishedTarget){
    try{
        $collector->collectTarget((int)$publishedTarget['target_id'],(int)$publishedTarget['tenant_id'],null);
        $result['metrics_collected']++;
    }catch(Throwable$exception){
        // La publication est déjà confirmée : un échec KPI ne doit jamais la remettre en file.
        $result['metrics_failed']++;
        $result['metrics_errors'][]=[
            'target_id'=>(int)($publishedTarget['target_id']??0),
            'message'=>$exception->getMessage(),
        ];
    }
}
echo json_encode($result,JSON_UNESCAPED_SLASHES).PHP_EOL;
