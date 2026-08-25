<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only\n");}
require dirname(__DIR__).'/config/config.php';
$limit=isset($argv[1])?(int)$argv[1]:20;
$result=(new SocialPublisherService())->processDue($limit);
echo json_encode($result,JSON_UNESCAPED_SLASHES).PHP_EOL;
