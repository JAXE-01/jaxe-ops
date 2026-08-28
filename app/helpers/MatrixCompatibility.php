<?php
class MatrixCompatibility {
 public static function rules(array$matrix):?array{
  $raw=$matrix['compatibility_json']??null;if($raw===null)return null;
  $rules=json_decode((string)$raw,true);if(!is_array($rules))throw new RuntimeException('Compatibilités de la matrice invalides.');return $rules;
 }
 public static function allowed(array$matrix,string$product,string$target):bool{
  $rules=self::rules($matrix);return $rules===null||in_array($target,(array)($rules[$product]??[]),true);
 }
 public static function assertIdea(array$matrix,array$idea):void{
  if(!self::allowed($matrix,trim((string)($idea['product_offer']??'')),trim((string)($idea['target_audience']??''))))throw new RuntimeException('Ce produit/service ne convient pas à cette cible selon les compatibilités de la matrice.');
 }
 public static function pairs(array$matrix,string$type,array$anchors):array{
  if(self::rules($matrix)===null)throw new RuntimeException('Définissez les compatibilités produit/cible avant de générer automatiquement.');
  $groups=[];
  foreach($anchors as$anchor){$pairs=[];foreach($matrix['product_list']as$product)foreach($matrix['target_list']as$target){if(($type==='Produit'?$product:$target)!==$anchor)continue;if(self::allowed($matrix,$product,$target))$pairs[]=['product'=>$product,'target'=>$target,'anchor'=>$anchor];}if(!$pairs)throw new RuntimeException('Aucune combinaison compatible pour : '.$anchor);$groups[]=$pairs;}
  return $groups;
 }
 public static function fromSelection(array$matrix,array$selection):array{
  $rules=[];foreach($matrix['product_list']as$i=>$product){$rules[$product]=[];foreach((array)($selection[$i]??[])as$index){if(!ctype_digit((string)$index)||!isset($matrix['target_list'][(int)$index]))throw new RuntimeException('Cible de compatibilité invalide.');$rules[$product][]=$matrix['target_list'][(int)$index];}$rules[$product]=array_values(array_unique($rules[$product]));}return $rules;
 }
}
