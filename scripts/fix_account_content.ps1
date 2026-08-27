$ErrorActionPreference='Stop';$root=Split-Path $PSScriptRoot -Parent
function Edit($path,$old,$new){$p=Join-Path $root $path;$s=[IO.File]::ReadAllText($p).Replace("`r`n","`n");if(!$s.Contains($old)){throw "Anchor missing $path"};[IO.File]::WriteAllText($p,$s.Replace($old,$new),[Text.UTF8Encoding]::new($false))}
Edit 'app/controllers/UserController.php' "protected `$moduleKey = 'user';" @'
protected $moduleKey = 'user';
    public function __construct(){parent::__construct();$this->model=new ManagedUserModel();}
'@
Edit 'app/controllers/UserController.php' "`$payload['password'] === null" "trim((string)`$payload['password']) === ''"
Edit 'app/views/crud/form.php' "<?= !empty(`$meta['required']) ? 'required' : '' ?>" "<?= !empty(`$meta['required']) && !((`$meta['type']??'')==='password' && !empty(`$record['id'])) ? 'required' : '' ?>"
Edit 'app/views/crud/form.php' "<?= `$inputType === 'password' ? 'autocomplete=`"new-password`"' : '' ?>" "<?= `$inputType === 'password' ? 'autocomplete=`"new-password`" placeholder=`"Laisser vide pour conserver le mot de passe actuel (modification)`"' : '' ?>"
Edit 'app/core/TeamInvitationService.php' 'public static function inspect(string $token): ?array {' @'
public function reactivate(int $membershipId): void {
        $q=$this->pdo->prepare("UPDATE tenant_memberships SET status='Actif' WHERE id=:id AND tenant_id=:tenant AND organization_id=:org AND status='Suspendu' AND joined_at IS NOT NULL");
        $q->execute(['id'=>$membershipId,'tenant'=>TenantGuard::tenantId(),'org'=>$this->organization['id']]);
        if(!$q->rowCount())throw new RuntimeException('Accès inaccessible ou invitation jamais acceptée : une invitation doit être renouvelée.');
    }
    public static function inspect(string $token): ?array {
'@
Edit 'app/controllers/TeamController.php' "}elseif(`$action==='suspend')" "}elseif(`$action==='reactivate'){`$service->reactivate((int)(`$_POST['membership_id']??0));`$this->flash('success','Accès réactivé dans cette entreprise.');}elseif(`$action==='suspend')"
Edit 'app/views/team/index.php' 'Accès actifs et invités' 'Tous les accès : actifs, invités et suspendus'
Edit 'app/views/team/index.php' "<?php if(`$m['membership_role']!=='Owner'&&`$m['status']!=='Suspendu'):?>" @'
<?php if($this->can('users.manage')&&$m['status']==='Suspendu'):?><form method="post"><input type="hidden" name="action" value="reactivate"><input type="hidden" name="membership_id" value="<?= (int)$m['membership_id'] ?>"><button class="button secondary">Réactiver</button></form><?php endif ?><?php if($this->can('users.manage')&&$m['membership_role']!=='Owner'&&$m['status']!=='Suspendu'):?>
'@
# Replace static requirements with the same server calculator used by autosave.
$p=Join-Path $root 'app/views/calendrier/contenu.php';$s=[IO.File]::ReadAllText($p);$a=$s.IndexOf('$contentRequirements = [');$b=$s.IndexOf('$tpackRefs = [',$a);if($a-lt 0 -or $b-lt 0){throw 'Requirements anchor'};$s=$s.Substring(0,$a)+"`$contentRequirements = ContentCompletion::requirements(`$deliverable);`n"+$s.Substring($b)
$a=$s.IndexOf('        <div class="info-banner">',$s.IndexOf('<?php if (!$canEdit): ?>'));$b=$s.IndexOf('    <?php endif; ?>',$a);if($a-lt 0 -or $b-lt 0){throw 'Progress markup anchor'}
$replacement=@'
        <link rel="stylesheet" href="<?= htmlspecialchars(app_url('/public/assets/content-completion.css')) ?>">
        <div data-content-completion aria-live="polite"></div>
        <script type="application/json" data-completion-initial><?= json_encode($contentRequirements,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
        <script src="<?= htmlspecialchars(app_url('/public/assets/content-completion.js')) ?>"></script>
'@
$s=$s.Substring(0,$a)+$replacement+"`n"+$s.Substring($b);[IO.File]::WriteAllText($p,$s,[Text.UTF8Encoding]::new($false))
Edit 'app/controllers/CalendrierController.php' "PipelineService::syncContentStatusByDeliverable((int) `$deliverableId);`n                if (`$isInlineAutosave) {`n                    `$this->respondJson(['ok' => true, 'autosaved' => true, 'message' => 'Brouillon enregistre.', 'at' => date('H:i:s')]);" @'
PipelineService::syncContentStatusByDeliverable((int) $deliverableId);
                if ($isInlineAutosave) {
                    $fresh=$this->calendrierModel->getContentWorkspace((int)$deliverableId,$this->currentUser());
                    $this->respondJson(['ok' => true, 'autosaved' => true, 'message' => 'Brouillon enregistre.', 'requirements'=>ContentCompletion::requirements($fresh?:[]), 'at' => date('H:i:s')]);
'@
Edit 'app/views/calendrier/contenu.php' "setStatus('Sauvegarde auto: ' + (result.json.at || ''), 'saved');" "if(window.updateContentCompletion)window.updateContentCompletion(result.json.requirements);`n                setStatus('Sauvegarde auto: ' + (result.json.at || ''), 'saved');"
Edit 'app/views/layouts/main.php' '</body>' "<script src=`"<?= htmlspecialchars(app_url('/public/assets/calendar-position.js')) ?>`"></script>`n</body>"
