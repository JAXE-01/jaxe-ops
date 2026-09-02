<?php foreach(['publication'=>['Par publication',$publicationReport],'monthly'=>['Synthèse mensuelle',$monthlyReport],'individual'=>['Historique des relevés par publication',$rows]] as $model=>[$heading,$items]): $columns=ReportPresentation::columns($model,$_GET); ?>
<section class="panel report-table-panel" <?= in_array($model,ReportPresentation::tables($_GET),true)?'':'hidden' ?>>
<div class="panel-head"><h2><?= $heading ?></h2><details class="report-columns"><summary>Colonnes / KPI</summary><div>
<input type="hidden" form="reporting-filter-form" name="columns[<?= $model ?>][]" value="">
<?php foreach(ReportPresentation::fields($model) as $key=>$label): ?><label><input type="checkbox" form="reporting-filter-form" name="columns[<?= $model ?>][]" value="<?= $key ?>" <?= in_array($key,$columns,true)?'checked':'' ?>> <?= htmlspecialchars($label) ?></label><?php endforeach ?>
</div></details></div>
<p class="panel-subtitle">↓ Importée · ↑ Publiée via Strax · — Indisponible · Même sélection de colonnes dans l’export.</p>
<div class="table-wrap compact-table"><table><thead><tr>
<?php foreach($columns as $key): ?><th><button type="button" class="report-sort" data-sort="<?= $key ?>" title="Trier : <?= htmlspecialchars(ReportPresentation::label($key)) ?>" aria-label="Trier : <?= htmlspecialchars(ReportPresentation::label($key)) ?>"><?= ReportPresentation::icon($key) ?> <?= ($filters['sort']??'date_publication')===$key?(($filters['direction']??'desc')==='asc'?'↑':'↓'):'' ?></button></th><?php endforeach ?>
</tr></thead><tbody><?php foreach($items as $row): ?><tr><?php foreach($columns as $key): ?><td><?= ReportPresentation::cell($row,$key) ?><?php if($model==='individual' && $canManage && $key===$columns[array_key_last($columns)]): ?><form class="report-delete" data-no-global-loader method="post" action="<?= htmlspecialchars(route_url('/reporting-metric/delete/'.(int)($row['id']??0))) ?>" onsubmit="return confirm('Supprimer cette collecte ?');"><button type="submit" aria-label="Supprimer cette collecte" title="Supprimer cette collecte">×</button></form><?php endif ?></td><?php endforeach ?></tr><?php endforeach ?>
<?php if(!$items): ?><tr><td colspan="<?= count($columns) ?>">Aucune publication pour cette sélection.</td></tr><?php endif ?>
</tbody></table></div></section>
<?php endforeach ?>
