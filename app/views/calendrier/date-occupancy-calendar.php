<?php
$occupancyMonth=new DateTimeImmutable($monthStart?:WorkingMonth::resolve().'-01');
$occupancyCounts=[];
foreach($scheduledPublicationDates as $entry)$occupancyCounts[(string)$entry['date_prevue']]=(int)$entry['total'];
$monthNames=[1=>'janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
?>
<details class="occupied-dates">
<summary>Voir les dates du calendrier</summary>
<div class="date-occupancy" aria-label="Dates déjà occupées dans ce calendrier mensuel">
<strong><?= $monthNames[(int)$occupancyMonth->format('n')].' '.$occupancyMonth->format('Y') ?></strong>
<div class="date-occupancy-grid">
<?php foreach(['L','M','M','J','V','S','D'] as $dayLabel): ?><span aria-hidden="true" class="weekday"><?= $dayLabel ?></span><?php endforeach ?>
<?php for($blank=1;$blank<(int)$occupancyMonth->format('N');$blank++): ?><span></span><?php endfor ?>
<?php for($day=1;$day<=(int)$occupancyMonth->format('t');$day++):
 $date=$occupancyMonth->format('Y-m-').str_pad((string)$day,2,'0',STR_PAD_LEFT);$count=$occupancyCounts[$date]??0;
 $label=$date.($count?' · '.$count.' autre(s) contenu(s) prévu(s)':' · aucun autre contenu prévu dans ce calendrier');
?>
<?php if($canEdit): ?><a href="#" data-select-content-date="<?= $date ?>" class="<?= $count?'occupied':'' ?>" title="<?= htmlspecialchars($label) ?>" aria-label="<?= htmlspecialchars($label) ?>"><?= $day ?><?= $count?'<small>'.$count.'</small>':'' ?></a>
<?php else: ?><span title="<?= htmlspecialchars($label) ?>" class="<?= $count?'occupied':'' ?>"><?= $day ?></span><?php endif ?>
<?php endfor ?>
</div><small>Les pastilles comptent les autres contenus de ce calendrier. Plusieurs publications le même jour restent possibles.</small>
</div></details>
