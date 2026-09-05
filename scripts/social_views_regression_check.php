<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__).'/config/config.php';
try{
 $db=Database::getConnection();$user=$db->query("SELECT * FROM users WHERE statut='Actif' AND role='Admin' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
 if(!$user)throw new RuntimeException('Admin local requis');
 $_SESSION['user']=$user;$_SESSION['user']['roles']=UserRoles::extractRoles($user);TenantContext::clear();
 $_SERVER['REQUEST_METHOD']='GET';$_SERVER['HTTP_HOST']='localhost';$_SERVER['REQUEST_URI']='/index.php/social-publishing';
 ob_start();try{(new SocialPublishingController())->index();$html=ob_get_contents();}finally{ob_end_clean();}
 foreach(['social-context.js','social-context.css','data-destination-hint','Gérer les comptes']as$expected)if(!str_contains($html,$expected))throw new RuntimeException('Publication : rendu incomplet '.$expected);
 $_SERVER['REQUEST_URI']='/index.php/social-connection';
 ob_start();try{(new SocialConnectionController())->index();$accountsHtml=ob_get_contents();}finally{ob_end_clean();}
 foreach(['data-destination-search','Comptes sociaux','network-logo']as$expected)if(!str_contains($accountsHtml,$expected))throw new RuntimeException('Comptes sociaux : rendu incomplet '.$expected);
 if(!preg_match('~social-oauth/connect/[^"?]+" target="_blank" rel="noopener noreferrer"~',$accountsHtml))throw new RuntimeException('La connexion OAuth doit ouvrir un nouvel onglet sécurisé.');
 $q=$db->prepare('SELECT p.id FROM projets p JOIN clients c ON c.id=p.client_id WHERE c.tenant_id=:tenant LIMIT 1');$q->execute(['tenant'=>TenantGuard::tenantId()]);$id=(int)$q->fetchColumn();
 if(!$id)throw new RuntimeException('Projet local requis');
 $_SERVER['REQUEST_URI']='/index.php/projet/edit/'.$id;
 ob_start();try{(new ProjetController())->edit($id);$html=ob_get_contents();}finally{ob_end_clean();}
 if(!str_contains($html,'social_pages_present')||!str_contains($html,'Pages et comptes de ce projet'))throw new RuntimeException('Sélecteur de pages absent du formulaire projet');
 echo "OK: rendu PHP publications, comptes séparés, icônes, OAuth en nouvel onglet et formulaire projet.\n";
}catch(Throwable$e){fwrite(STDERR,get_class($e).': '.$e->getMessage().PHP_EOL);exit(1);}
