<?php if(!empty($currentUser)):
$contextPath=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH)?:'';
$detailContext=(bool)preg_match('~/calendrier/(contenu|task|brief|script)/~',$contextPath);
$monthAction=$detailContext?route_url('/calendrier'):$contextPath;
?>
<div class="work-month-bar">
<form method="get" action="<?= htmlspecialchars($monthAction) ?>" class="work-month-form">
<?php foreach(['client_id','project_id','matrix_id','section'] as $contextKey): if(isset($_GET[$contextKey])&&is_scalar($_GET[$contextKey])): ?>
<input type="hidden" name="<?= $contextKey ?>" value="<?= htmlspecialchars((string)$_GET[$contextKey]) ?>">
<?php endif;endforeach ?>
<label for="global-working-month">Mois de travail</label>
<input id="global-working-month" type="month" name="month" value="<?= htmlspecialchars(WorkingMonth::resolve()) ?>" required>
<button type="submit" class="button secondary" title="Afficher ce mois" aria-label="Afficher ce mois">→</button>
<span class="work-month-hint"><?= $detailContext?'Changer de mois ouvre le calendrier, sans déplacer ce contenu.':'Contexte conservé dans votre espace de travail.' ?></span>
</form>
</div>
<style>
.work-month-bar{position:sticky;top:0;z-index:30;background:rgba(255,255,255,.97);border:1px solid #dfe7f0;border-radius:12px;padding:8px 12px;margin-bottom:14px;box-shadow:0 3px 12px #132f5010}
.work-month-form{display:flex;align-items:center;gap:10px;margin:0;flex-wrap:wrap}
.work-month-form label{font-size:12px;font-weight:700;color:#536b88}
.work-month-form input[type=month]{width:168px;min-height:34px;padding:5px 8px}
.work-month-form .button{padding:5px 12px;min-height:34px}
.work-month-hint{font-size:12px;color:#6c7c92}
@media(max-width:700px){.work-month-hint{display:none}.work-month-bar{top:0}.work-month-form{gap:6px}}
</style>
<?php endif ?>
