<?php
class InstagramMetricMapper {
    public static function fromMedia(array $data):array {
        $metrics=['_availability'=>[]];
        foreach(['like_count'=>'likes','comments_count'=>'commentaires'] as $remote=>$local){
            $value=$data[$remote]??null;$available=is_numeric($value);
            $metrics[$local]=$available?max(0,(int)$value):null;
            $metrics['_availability'][$local]=['status'=>$available?'available':'unavailable','source'=>$remote];
        }
        $metrics['_content_type']=strtoupper((string)($data['media_product_type']??''))==='REELS'?'reel':ReportPresentation::type(strtolower((string)($data['media_type']??'')));
        return $metrics;
    }
}
