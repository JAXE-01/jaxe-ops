<link rel="stylesheet" href="<?= htmlspecialchars(app_url('/public/assets/matrix-workspace.css')) ?>">
<?php
$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$hidden=static function($matrixId,$clientId,$projectId,$month)use($e){echo '<input type="hidden" name="matrix_id" value="'.(int)$matrixId.'"><input type="hidden" name="client_id" value="'.(int)$clientId.'"><input type="hidden" name="project_id" value="'.(int)$projectId.'"><input type="hidden" name="month" value="'.$e($month).'">';};
$refs=['target'=>['Cibles','target_audience'],'objective'=>['Objectifs','objective'],'problem'=>['Besoins','problem_need'],'product'=>['Produits / offres','product_offer'],'format'=>['Formats creatifs','creative_format'],'cta'=>['Appels a l’action','call_to_action'],'platform'=>['Canaux','platform']];
$drafts=count(array_filter($ideas,fn($i)=>$i['status']==='Brouillon'));
$validated=count(array_filter($ideas,fn($i)=>$i['status']==='Validee'));
?>
<section class="mx-page">
 <header class="mx-heading"><div><span class="mx-kicker">Studio editorial</span><h1>Matrice de creation</h1><p>Generez, enrichissez et distribuez vos idees dans les calendriers clients.</p></div><div class="mx-progress"><span class="on">Idees</span><i></i><span>Brief & script</span><i></i><span>Calendrier</span></div></header>

 <form method="get" action="<?= $e(route_url('/matrix')) ?>" class="panel mx-context">
  <div><span class="mx-kicker">Contexte actif</span><strong>Ou souhaitez-vous travailler ?</strong></div>
  <label>Client<select name="client_id" data-client-change><?php foreach($clients as$c):?><option value="<?= (int)$c['id']?>" <?= (int)$clientId===(int)$c['id']?'selected':''?>><?= $e($c['entreprise']?:$c['nom'])?></option><?php endforeach?></select></label>
  <label>Projet<select name="project_id"><?php foreach($projects as$p):?><option value="<?= (int)$p['id']?>" <?= (int)$projectId===(int)$p['id']?'selected':''?>><?= $e($p['nom'])?></option><?php endforeach?></select></label>
  <label>Mois de depart<input type="month" name="month" value="<?= $e($month)?>"></label><button class="button">Actualiser</button>
 </form>

 <?php if(!$clients):?><div class="panel mx-empty"><h2>Aucun client accessible</h2><p>Ajoutez un client avant de creer une matrice.</p></div><?php else:?>
 <div class="mx-shell">
  <aside class="panel mx-sidebar">
   <div class="mx-side-title"><div><span class="mx-kicker">Bibliotheque</span><h2>Matrices</h2></div><button type="button" data-new-matrix>+</button></div>
   <nav><?php foreach($matrices as$m):?><a class="<?= $matrix&&(int)$matrix['id']===(int)$m['id']?'active':''?>" href="<?= $e(route_url('/matrix').'?client_id='.$clientId.'&project_id='.$projectId.'&month='.$month.'&matrix_id='.$m['id'])?>"><b><?= $e(mb_substr($m['name'],0,1))?></b><span><strong><?= $e($m['name'])?></strong><small><?= $e($m['default_deliverable_type'])?> · <?= $e($m['default_format']?:'Multi-format')?></small></span></a><?php endforeach?></nav>
   <?php if($matrix):?><div class="mx-side-actions"><form method="post"><?php $hidden($matrix['id'],$clientId,$projectId,$month)?><button class="button secondary" name="action" value="clone_matrix">Dupliquer</button></form><form method="post" onsubmit="return confirm('Supprimer cette matrice ? Les contenus deja places au calendrier seront conserves.')"><?php $hidden($matrix['id'],$clientId,$projectId,$month)?><button class="mx-danger-link" name="action" value="delete_matrix">Supprimer</button></form></div><?php endif?>
  </aside>

  <main class="mx-main">
   <details class="panel mx-settings" <?= !$matrix?'open':''?> data-config><summary><div><span class="mx-kicker">Configuration</span><strong><?= $matrix?'Parametres de '.$e($matrix['name']):'Nouvelle matrice'?></strong></div><span>Modifier les referentiels <b>⌄</b></span></summary>
    <form method="post" class="mx-grid"><?php $hidden($matrix['id']??0,$clientId,$projectId,$month)?><input type="hidden" name="action" value="<?= $matrix?'update_matrix':'create_matrix'?>"><label class="span-2">Nom<input required name="name" value="<?= $e($matrix['name']??'')?>"></label><label class="span-2">Description<input name="description" value="<?= $e($matrix['description']??'')?>"></label><?php foreach($refs as$key=>$meta):?><label><?= $e($meta[0])?><textarea rows="4" name="<?= $key?>_options"><?= $e(implode("\n",$matrix[$key.'_list']??[]))?></textarea></label><?php endforeach?><label>Nature par defaut<select name="default_deliverable_type"><option <?= ($matrix['default_deliverable_type']??'Video')==='Video'?'selected':''?>>Video</option><option <?= ($matrix['default_deliverable_type']??'')==='Visuel'?'selected':''?>>Visuel</option></select></label><label>Format favori<input name="default_format" value="<?= $e($matrix['default_format']??'')?>"></label><div class="span-2 mx-end"><button class="button"><?= $matrix?'Enregistrer':'Creer la matrice'?></button></div></form>
   </details>

   <?php if($matrix&&$projectId):?>
   <section class="mx-tabs panel">
    <div class="mx-tabbar"><button type="button" class="active" data-tab="single">Idee manuelle</button><button type="button" data-tab="auto">Combinaisons automatiques</button></div>
    <div data-pane="single">
     <div class="mx-section-title"><div><span class="mx-kicker">Creation complete</span><h2>Idee, brief et script</h2><p>Le brief et le script peuvent etre saisis maintenant ou completes plus tard.</p></div><span class="mx-pill">Manuel</span></div>
     <form method="post" class="mx-grid"><?php $hidden($matrix['id'],$clientId,$projectId,$month)?><input type="hidden" name="action" value="add_idea"><?php foreach($refs as$key=>$meta):?><label><?= $e(rtrim($meta[0],'s'))?><select name="<?= $e($meta[1])?>"><?php foreach($matrix[$key.'_list'] as$o):?><option <?= $key==='format'&&$o===($matrix['default_format']??'')?'selected':''?>><?= $e($o)?></option><?php endforeach?></select></label><?php endforeach?><label>Nature<select name="deliverable_type"><option <?= $matrix['default_deliverable_type']==='Video'?'selected':''?>>Video</option><option <?= $matrix['default_deliverable_type']==='Visuel'?'selected':''?>>Visuel</option></select></label><label>Priorite<select name="priority"><option>Haute</option><option selected>Moyenne</option><option>Basse</option></select></label><label class="span-2">Idee / accroche<input required name="hook_idea" placeholder="La promesse centrale du contenu"></label><label class="span-2">Brief<textarea name="generated_brief" rows="5" placeholder="Laissez vide pour generer automatiquement un brief structure"></textarea></label><label class="span-2">Script<textarea name="script_content" rows="7" placeholder="Accroche, developpement, preuve, conclusion et CTA"></textarea></label><div class="span-2 mx-end"><span>Enregistrement dans la banque, sans affectation immediate.</span><button class="button">Ajouter l’idee</button></div></form>
    </div>
    <div data-pane="auto" hidden>
     <div class="mx-section-title"><div><span class="mx-kicker">Generateur</span><h2>Creer une serie coherente</h2><p>Fixez l’element principal. Les autres dimensions seront combinees automatiquement.</p></div><span class="mx-pill purple">Automatique</span></div>
     <form method="post" class="mx-auto-form"><?php $hidden($matrix['id'],$clientId,$projectId,$month)?><input type="hidden" name="action" value="generate_combinations"><label>Element principal<select name="anchor_type" data-anchor-type><option>Produit</option><option>Cible</option></select></label><fieldset class="mx-anchor-field"><legend>Valeurs principales <small>Selectionnez une, plusieurs ou toutes les valeurs</small></legend><label class="mx-anchor-all"><input type="checkbox" data-anchor-all><span>Tout selectionner</span></label><div class="mx-anchor-options" data-anchor-options><?php foreach($matrix['product_list'] as$o):?><label><input type="checkbox" name="anchor_values[]" value="<?= $e($o)?>"><span><?= $e($o)?></span></label><?php endforeach?></div></fieldset><label>Nombre d’idees<input type="number" min="1" max="30" name="combination_count" value="6"></label><label class="mx-check"><input type="checkbox" name="with_script" value="1" checked><span>Generer aussi une trame de script</span></label><button class="button">Generer la banque</button></form>
     <script type="application/json" data-products><?= json_encode($matrix['product_list'],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG)?></script><script type="application/json" data-targets><?= json_encode($matrix['target_list'],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG)?></script>
    </div>
   </section>
   <?php endif?>
  </main>
 </div>

 <?php if($matrix&&$projectId):?>
 <section class="panel mx-bank">
  <div class="mx-bank-head"><div><span class="mx-kicker">Reservoir editorial</span><h2>Banque d’idees <em><?= count($ideas)?></em></h2><p>Les idees affectees disparaissent automatiquement de cette banque.</p></div><div class="mx-metrics"><span><b><?= $drafts?></b>Brouillons</span><span><b><?= $validated?></b>Validees</span></div></div>
  <form method="post" id="mx-bulk"><?php $hidden($matrix['id'],$clientId,$projectId,$month)?></form>
  <div class="mx-bulkbar"><label><input type="checkbox" data-select-all> Tout selectionner</label><div><button class="button secondary" form="mx-bulk" name="action" value="validate_ideas">Valider</button><button class="button danger-soft" form="mx-bulk" name="action" value="delete_ideas" onclick="return confirm('Supprimer les idees selectionnees ?')">Supprimer</button></div></div>
  <div class="mx-ideas"><?php foreach($ideas as$i):?><article class="mx-idea"><input class="mx-select" type="checkbox" form="mx-bulk" name="idea_ids[]" value="<?= (int)$i['id']?>"><div class="mx-idea-body"><div class="mx-idea-top"><div><span class="mx-pill <?= $i['generation_mode']==='Combinaison'?'purple':''?>"><?= $e($i['generation_mode'])?></span><span class="mx-status <?= strtolower($e($i['status']))?>"><?= $e($i['status'])?></span></div><small><?= $e($i['deliverable_type'])?> · <?= $e($i['creative_format'])?></small></div><h3><?= $e($i['hook_idea'])?></h3><p><?= $e(mb_strimwidth($i['generated_brief']??'',0,220,'…'))?></p><div class="mx-tags"><span><?= $e($i['product_offer'])?></span><span><?= $e($i['target_audience'])?></span><span><?= $e($i['platform'])?></span></div><details><summary>Modifier l’idee, le brief ou le script <b>⌄</b></summary><form method="post" class="mx-grid"><?php $hidden($matrix['id'],$clientId,$projectId,$month)?><input type="hidden" name="action" value="update_idea"><input type="hidden" name="idea_id" value="<?= (int)$i['id']?>"><label class="span-2">Idee<input name="hook_idea" required value="<?= $e($i['hook_idea'])?>"></label><?php foreach($refs as$key=>$meta):$field=$meta[1];?><label><?= $e(rtrim($meta[0],'s'))?><input name="<?= $e($field)?>" value="<?= $e($i[$field])?>"></label><?php endforeach?><label>Nature<select name="deliverable_type"><option <?= $i['deliverable_type']==='Video'?'selected':''?>>Video</option><option <?= $i['deliverable_type']==='Visuel'?'selected':''?>>Visuel</option></select></label><label>Priorite<select name="priority"><?php foreach(['Haute','Moyenne','Basse']as$o):?><option <?= $i['priority']===$o?'selected':''?>><?= $o?></option><?php endforeach?></select></label><label>Statut<select name="status"><?php foreach(['Brouillon','Validee','Ecartee']as$o):?><option <?= $i['status']===$o?'selected':''?>><?= $o?></option><?php endforeach?></select></label><label class="span-2">Brief<textarea rows="5" name="generated_brief"><?= $e($i['generated_brief'])?></textarea></label><label class="span-2">Script<textarea rows="7" name="script_content"><?= $e($i['script_content'])?></textarea></label><div class="span-2 mx-end"><button class="button">Enregistrer les modifications</button></div></form></details></div></article><?php endforeach?><?php if(!$ideas):?><div class="mx-empty"><div>✦</div><h3>La banque est vide</h3><p>Creez une idee manuelle ou lancez des combinaisons automatiques.</p></div><?php endif?></div>
  <?php if($ideas):?><div class="mx-assign"><div><span class="mx-kicker">Affectation au calendrier</span><h3>Repartir la selection</h3><p>Distribution circulaire et equilibree entre les mois choisis.</p></div><label>Premier mois<input form="mx-bulk" type="month" name="start_month" value="<?= $e($month)?>"></label><label>Etaler sur<select form="mx-bulk" name="spread_months"><option value="1">1 mois</option><?php for($n=2;$n<=12;$n++):?><option value="<?= $n?>"><?= $n?> mois</option><?php endfor?></select></label><button class="button" form="mx-bulk" name="action" value="assign_ideas">Affecter et retirer de la banque</button></div><?php endif?>
 </section>
 <?php endif;endif?>
</section>
<script>
document.querySelector('[data-client-change]')?.addEventListener('change',e=>e.target.form.submit());
document.querySelectorAll('[data-tab]').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('[data-tab]').forEach(x=>x.classList.toggle('active',x===b));document.querySelectorAll('[data-pane]').forEach(p=>p.hidden=p.dataset.pane!==b.dataset.tab)}));
document.querySelector('[data-select-all]')?.addEventListener('change',e=>document.querySelectorAll('.mx-select').forEach(x=>x.checked=e.target.checked));
document.querySelector('[data-new-matrix]')?.addEventListener('click',()=>{const d=document.querySelector('[data-config]');if(d){d.open=true;d.querySelector('[name=action]').value='create_matrix';d.querySelector('[name=matrix_id]').value='0';d.querySelector('[name=name]').value='';d.scrollIntoView({behavior:'smooth'})}});
const anchor=document.querySelector('[data-anchor-type]'),options=document.querySelector('[data-anchor-options]'),all=document.querySelector('[data-anchor-all]');
const escapeHtml=x=>String(x).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const renderAnchors=()=>{const data=JSON.parse(document.querySelector(anchor.value==='Produit'?'[data-products]':'[data-targets]').textContent);options.innerHTML=data.map(x=>`<label><input type="checkbox" name="anchor_values[]" value="${escapeHtml(x)}"><span>${escapeHtml(x)}</span></label>`).join('');all.checked=false;all.indeterminate=false};
anchor?.addEventListener('change',renderAnchors);
all?.addEventListener('change',()=>options.querySelectorAll('input').forEach(x=>x.checked=all.checked));
options?.addEventListener('change',()=>{const boxes=[...options.querySelectorAll('input')],checked=boxes.filter(x=>x.checked).length;all.checked=checked===boxes.length&&boxes.length>0;all.indeterminate=checked>0&&checked<boxes.length});
const ideaCards=[...document.querySelectorAll('.mx-idea')];
const closeIdeaCards=except=>ideaCards.forEach(card=>{if(card!==except){const detail=card.querySelector('details');if(detail)detail.open=false;card.classList.remove('is-open');card.setAttribute('aria-expanded','false')}});
ideaCards.forEach(card=>{
 const detail=card.querySelector('details'); if(!detail)return;
 card.tabIndex=0; card.setAttribute('aria-expanded',detail.open?'true':'false');
 detail.addEventListener('toggle',()=>{card.classList.toggle('is-open',detail.open);card.setAttribute('aria-expanded',detail.open?'true':'false');if(detail.open)closeIdeaCards(card)});
 card.addEventListener('click',event=>{event.stopPropagation();if(event.target.closest('input,button,select,textarea,a,label,summary,form'))return;detail.open=!detail.open;if(detail.open){closeIdeaCards(card);requestAnimationFrame(()=>card.scrollIntoView({behavior:'smooth',block:'nearest'}))}});
 card.addEventListener('keydown',event=>{if((event.key==='Enter'||event.key===' ')&&event.target===card){event.preventDefault();detail.open=!detail.open;if(detail.open)closeIdeaCards(card)}});
});
document.addEventListener('click',event=>{if(!event.target.closest('.mx-idea'))closeIdeaCards(null)});
document.addEventListener('keydown',event=>{if(event.key==='Escape')closeIdeaCards(null)});</script>
