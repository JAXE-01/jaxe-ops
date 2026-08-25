<link rel="stylesheet" href="<?= htmlspecialchars(app_url('/public/assets/matrix.css')) ?>">
<?php
$esc = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$counts = ['Brouillon'=>0, 'Validee'=>0, 'Synchronisee'=>0, 'Ecartee'=>0];
foreach ($ideas as $idea) { $counts[$idea['status']] = ($counts[$idea['status']] ?? 0) + 1; }
$matrixOptions = [
    'target' => ['label'=>'Cibles','field'=>'target_audience'],
    'objective' => ['label'=>'Objectifs','field'=>'objective'],
    'problem' => ['label'=>'Problemes / besoins','field'=>'problem_need'],
    'product' => ['label'=>'Produits / offres','field'=>'product_offer'],
    'format' => ['label'=>'Formats creatifs','field'=>'creative_format'],
    'cta' => ['label'=>'Appels a l’action','field'=>'call_to_action'],
    'platform' => ['label'=>'Canaux','field'=>'platform'],
];
?>
<section class="matrix-page">
    <header class="matrix-hero">
        <div>
            <span class="matrix-eyebrow">Planification editoriale</span>
            <h1>Matrice de creation</h1>
            <p>Structurez les idees, validez-les en equipe puis envoyez-les sans doublon vers le calendrier mensuel du client.</p>
        </div>
        <div class="matrix-flow" aria-label="Processus de travail">
            <span class="active">1. Composer</span><i></i><span>2. Valider</span><i></i><span>3. Synchroniser</span>
        </div>
    </header>

    <form method="get" action="<?= $esc(route_url('/matrix')) ?>" class="matrix-context panel">
        <div class="matrix-context-title"><strong>Contexte de travail</strong><span>Un client, un projet et un mois</span></div>
        <label>Client<select name="client_id" data-matrix-client><?php foreach ($clients as $client): ?><option value="<?= (int)$client['id'] ?>" <?= (int)$clientId===(int)$client['id']?'selected':'' ?>><?= $esc($client['entreprise'] ?: $client['nom']) ?></option><?php endforeach; ?></select></label>
        <label>Projet<select name="project_id"><?php foreach ($projects as $project): ?><option value="<?= (int)$project['id'] ?>" <?= (int)$projectId===(int)$project['id']?'selected':'' ?>><?= $esc($project['nom']) ?></option><?php endforeach; ?></select></label>
        <label>Mois<input type="month" name="month" value="<?= $esc($month) ?>"></label>
        <button class="button" type="submit">Afficher</button>
    </form>

    <?php if (!$clients): ?>
        <div class="panel matrix-empty"><h2>Aucun client accessible</h2><p>Creez un client ou autorisez cette agence a gerer une entreprise avant de construire une matrice.</p></div>
    <?php else: ?>
    <div class="matrix-stats">
        <article><span>Idees du mois</span><strong><?= count($ideas) ?></strong></article>
        <article><span>A valider</span><strong><?= $counts['Brouillon'] ?></strong></article>
        <article><span>Validees</span><strong><?= $counts['Validee'] ?></strong></article>
        <article class="success"><span>Synchronisees</span><strong><?= $counts['Synchronisee'] ?></strong></article>
    </div>

    <div class="matrix-layout">
        <aside class="panel matrix-library">
            <div class="matrix-section-head"><div><span class="matrix-eyebrow">Bibliotheque</span><h2>Mes matrices</h2></div><button type="button" class="matrix-icon-button" data-matrix-new title="Nouvelle matrice">+</button></div>
            <nav>
                <?php foreach ($matrices as $item): ?>
                    <a class="matrix-template <?= $matrix && (int)$matrix['id']===(int)$item['id']?'active':'' ?>" href="<?= $esc(route_url('/matrix').'?client_id='.$clientId.'&project_id='.$projectId.'&month='.$month.'&matrix_id='.(int)$item['id']) ?>">
                        <span><?= $esc(mb_substr($item['name'],0,1)) ?></span><div><strong><?= $esc($item['name']) ?></strong><small><?= $esc($item['default_deliverable_type']) ?> · <?= $esc($item['default_format'] ?: 'Formats multiples') ?></small></div>
                    </a>
                <?php endforeach; ?>
                <?php if (!$matrices): ?><p class="matrix-muted">Aucune matrice pour ce client. Creez la premiere.</p><?php endif; ?>
            </nav>
            <?php if ($matrix): ?>
            <form method="post" class="matrix-clone-form"><input type="hidden" name="action" value="clone_matrix"><input type="hidden" name="matrix_id" value="<?= (int)$matrix['id'] ?>"><input type="hidden" name="client_id" value="<?= (int)$clientId ?>"><input type="hidden" name="project_id" value="<?= (int)$projectId ?>"><input type="hidden" name="month" value="<?= $esc($month) ?>"><button class="button secondary full" type="submit">Dupliquer cette matrice</button></form>
            <?php endif; ?>
        </aside>

        <main class="matrix-main">
            <details class="panel matrix-config" <?= !$matrix?'open':'' ?> data-matrix-config>
                <summary><div><span class="matrix-eyebrow">Configuration</span><strong><?= $matrix?'Modifier la matrice':'Creer une matrice' ?></strong></div><span>Nom, references et formats <b>⌄</b></span></summary>
                <form method="post" class="matrix-config-form">
                    <input type="hidden" name="action" value="<?= $matrix?'update_matrix':'create_matrix' ?>"><input type="hidden" name="matrix_id" value="<?= (int)($matrix['id']??0) ?>"><input type="hidden" name="client_id" value="<?= (int)$clientId ?>"><input type="hidden" name="project_id" value="<?= (int)$projectId ?>"><input type="hidden" name="month" value="<?= $esc($month) ?>">
                    <label class="wide">Nom<input required name="name" value="<?= $esc($matrix['name']??'Matrice editoriale') ?>"></label>
                    <label class="wide">Description<input name="description" value="<?= $esc($matrix['description']??'') ?>" placeholder="Usage et ligne editoriale de cette matrice"></label>
                    <?php foreach ($matrixOptions as $key=>$meta): ?><label><?= $esc($meta['label']) ?><textarea name="<?= $key ?>_options" rows="4" placeholder="Une option par ligne"><?= $esc(implode("\n", $matrix[$key.'_list']??[])) ?></textarea></label><?php endforeach; ?>
                    <label>Nature par defaut<select name="default_deliverable_type"><option <?= ($matrix['default_deliverable_type']??'Video')==='Video'?'selected':'' ?>>Video</option><option <?= ($matrix['default_deliverable_type']??'')==='Visuel'?'selected':'' ?>>Visuel</option></select></label>
                    <label>Format favori<input name="default_format" value="<?= $esc($matrix['default_format']??'') ?>" placeholder="Ex. Reel 30 s, Carrousel"></label>
                    <div class="wide matrix-form-actions"><button class="button" type="submit"><?= $matrix?'Enregistrer la configuration':'Creer la matrice' ?></button></div>
                </form>
            </details>

            <?php if ($matrix && $projectId): ?>
            <section class="panel matrix-composer">
                <div class="matrix-section-head"><div><span class="matrix-eyebrow">Composition guidee</span><h2>Construire une idee</h2><p><?= $esc($matrix['name']) ?></p></div><span class="matrix-badge">Brouillon</span></div>
                <form method="post" class="matrix-composer-form">
                    <input type="hidden" name="action" value="add_idea"><input type="hidden" name="matrix_id" value="<?= (int)$matrix['id'] ?>"><input type="hidden" name="client_id" value="<?= (int)$clientId ?>"><input type="hidden" name="project_id" value="<?= (int)$projectId ?>"><input type="hidden" name="month" value="<?= $esc($month) ?>">
                    <?php foreach ($matrixOptions as $key=>$meta): ?><label><?= $esc(rtrim($meta['label'],'s')) ?><select name="<?= $esc($meta['field']) ?>"><?php foreach ($matrix[$key.'_list'] as $option): ?><option <?= $key==='format' && $option===($matrix['default_format']??'')?'selected':'' ?>><?= $esc($option) ?></option><?php endforeach; ?></select></label><?php endforeach; ?>
                    <label>Nature du livrable<select name="deliverable_type"><option <?= $matrix['default_deliverable_type']==='Video'?'selected':'' ?>>Video</option><option <?= $matrix['default_deliverable_type']==='Visuel'?'selected':'' ?>>Visuel</option></select></label>
                    <label>Priorite<select name="priority"><option>Haute</option><option selected>Moyenne</option><option>Basse</option></select></label>
                    <label class="wide">Accroche ou idee centrale<input required name="hook_idea" placeholder="Ex. 3 erreurs qui fragilisent votre emballage"></label>
                    <div class="wide matrix-form-actions"><span>L’idee restera modifiable dans sa fiche contenu apres synchronisation.</span><button class="button" type="submit">Ajouter a la banque</button></div>
                </form>
            </section>
            <?php endif; ?>
        </main>
    </div>

    <?php if ($matrix && $projectId): ?>
    <section class="panel matrix-bank">
        <div class="matrix-section-head"><div><span class="matrix-eyebrow">Production du mois</span><h2>Banque d’idees</h2><p>Validez uniquement les idees pretes a occuper un emplacement du calendrier.</p></div><a class="button secondary" href="<?= $esc(route_url('/calendrier/projet/'.$projectId).'?month='.$month.'-01') ?>">Voir le calendrier</a></div>
        <form method="post"><input type="hidden" name="matrix_id" value="<?= (int)$matrix['id'] ?>"><input type="hidden" name="client_id" value="<?= (int)$clientId ?>"><input type="hidden" name="project_id" value="<?= (int)$projectId ?>"><input type="hidden" name="month" value="<?= $esc($month) ?>">
            <div class="matrix-table-wrap"><table><thead><tr><th><input type="checkbox" data-matrix-all></th><th>Idee</th><th>Format</th><th>Canal</th><th>Priorite</th><th>Statut</th></tr></thead><tbody>
            <?php foreach ($ideas as $idea): ?><tr><td><input type="checkbox" name="idea_ids[]" value="<?= (int)$idea['id'] ?>" <?= $idea['status']==='Synchronisee'?'disabled':'' ?>></td><td><strong><?= $esc($idea['hook_idea']) ?></strong><small><?= $esc($idea['objective']) ?> · <?= $esc($idea['target_audience']) ?></small></td><td><?= $esc($idea['deliverable_type']) ?><small><?= $esc($idea['creative_format']) ?></small></td><td><?= $esc($idea['platform']) ?></td><td><span class="matrix-priority <?= strtolower($esc($idea['priority'])) ?>"><?= $esc($idea['priority']) ?></span></td><td><span class="matrix-status status-<?= strtolower($esc($idea['status'])) ?>"><?= $esc($idea['status']) ?></span></td></tr><?php endforeach; ?>
            <?php if (!$ideas): ?><tr><td colspan="6" class="matrix-empty-row">Aucune idee pour ce projet et ce mois.</td></tr><?php endif; ?>
            </tbody></table></div>
            <div class="matrix-bank-actions"><div><button class="button secondary" name="action" value="discard_ideas">Ecarter</button><button class="button secondary" name="action" value="validate_ideas">Valider la selection</button></div><button class="button" name="action" value="sync_ideas">Synchroniser les idees validees</button></div>
        </form>
    </section>
    <?php endif; endif; ?>
</section>
<script>
document.querySelector('[data-matrix-client]')?.addEventListener('change', e => { e.target.form.querySelector('[name="project_id"]').value=''; e.target.form.submit(); });
document.querySelector('[data-matrix-all]')?.addEventListener('change', e => document.querySelectorAll('input[name="idea_ids[]"]:not(:disabled)').forEach(box => box.checked=e.target.checked));
document.querySelector('[data-matrix-new]')?.addEventListener('click', () => { const d=document.querySelector('[data-matrix-config]'); if(d){d.open=true; d.querySelector('[name="action"]').value='create_matrix'; d.querySelector('[name="matrix_id"]').value='0'; d.querySelector('[name="name"]').value=''; d.scrollIntoView({behavior:'smooth'});} });
</script>
