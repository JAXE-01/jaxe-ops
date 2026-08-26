<?php
class OnboardingController extends Controller {
    public function __construct(){parent::__construct();$this->requireAuth();}
    public function index(){
        $pdo=Database::getConnection();$tenant=TenantGuard::tenantId();$org=OrganizationContext::forUser($this->currentUser())?:[];$orgId=(int)($org['id']??0);$clientCompany=($org['account_type']??'')==='ClientCompany';
        $count=function(string$sql,array$params=[])use($pdo):int{$stmt=$pdo->prepare($sql);$stmt->execute($params);return(int)$stmt->fetchColumn();};
        $clients=$count('SELECT COUNT(*) FROM clients WHERE tenant_id=:tenant AND statut=\'Actif\'',['tenant'=>$tenant]);
        $projects=$count('SELECT COUNT(*) FROM projets p JOIN clients c ON c.id=p.client_id WHERE c.tenant_id=:tenant',['tenant'=>$tenant]);
        $matrices=$count('SELECT COUNT(*) FROM content_matrices WHERE tenant_id=:tenant AND status=\'Active\'',['tenant'=>$tenant]);
        $ideas=$count('SELECT COUNT(*) FROM matrix_ideas WHERE tenant_id=:tenant',['tenant'=>$tenant]);
        $members=$count("SELECT COUNT(*) FROM tenant_memberships WHERE tenant_id=:tenant AND organization_id=:org AND status='Actif'",['tenant'=>$tenant,'org'=>$orgId]);
        $steps=[
            ['title'=>'Votre espace est vérifié','description'=>'Organisation et compte propriétaire opérationnels.','done'=>$orgId>0,'href'=>route_url('/account'),'action'=>'Voir mon espace'],
            ['title'=>$clientCompany?'Votre fiche entreprise est prête':'Ajoutez votre premier client','description'=>$clientCompany?'Elle servira de contexte à vos projets et calendriers.':'Créez un client externe ou connectez une entreprise inscrite.','done'=>$clients>0,'href'=>route_url($clientCompany?'/account':'/client/create'),'action'=>$clientCompany?'Vérifier':'Ajouter un client'],
            ['title'=>'Créez votre premier projet','description'=>'Définissez la période, les quotas et les responsables.','done'=>$projects>0,'href'=>route_url('/projet/create'),'action'=>'Créer un projet'],
            ['title'=>'Configurez une matrice','description'=>'Structurez vos cibles, offres, objectifs, formats et CTA.','done'=>$matrices>0,'href'=>route_url('/matrix'),'action'=>'Ouvrir la matrice'],
            ['title'=>'Créez au moins cinq idées','description'=>'Ajoutez un brief ou un script, puis affectez les idées au calendrier.','done'=>$ideas>=5,'href'=>route_url('/matrix'),'action'=>'Créer des idées'],
            ['title'=>'Invitez un collaborateur','description'=>'Chaque membre rejoint votre entreprise avec son propre mot de passe.','done'=>$members>=2,'href'=>route_url('/team'),'action'=>'Inviter mon équipe'],
        ];
        $done=count(array_filter($steps,static fn($step)=>$step['done']));$progress=(int)round(($done/count($steps))*100);
        $this->render('onboarding/index',['pageTitle'=>'Démarrage','organization'=>$org,'steps'=>$steps,'progress'=>$progress,'done'=>$done]);
    }
}
