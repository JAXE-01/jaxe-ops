<?php
/** References are always scoped to the content's client and active tenant. */
class ContentMatrixReferences {
    public static function load(int $clientId, int $matrixId = 0): array {
        $q = Database::getConnection()->prepare("SELECT * FROM content_matrices WHERE tenant_id=:tenant AND client_id=:client AND status='Active' ORDER BY name,id");
        $q->execute(['tenant'=>TenantGuard::tenantId(),'client'=>$clientId]);
        $matrices=$q->fetchAll(PDO::FETCH_ASSOC);
        $selected=null;
        foreach($matrices as $matrix) if((int)$matrix['id']===$matrixId) $selected=$matrix;
        if($matrixId>0&&!$selected) throw new RuntimeException('Matrice inaccessible pour ce client.');
        if(!$selected&&count($matrices)===1) $selected=$matrices[0];
        $refs=[];
        foreach(['targets'=>'target','objectives'=>'objective','problems'=>'problem','products'=>'product','formats'=>'format','ctas'=>'cta','platforms'=>'platform'] as $key=>$column) {
            $refs[$key]=$selected ? (json_decode((string)$selected[$column.'_options'],true)?:[]) : [];
        }
        return ['matrices'=>$matrices,'selected'=>$selected,'refs'=>$refs];
    }
}
