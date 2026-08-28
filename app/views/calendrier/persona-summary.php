<?php
$personaSummary=[];
$q=Database::getConnection()->prepare('SELECT id,nom_persona,profession,objectif,probleme,freins,canaux FROM personas WHERE client_id=:client');
$q->execute(['client'=>(int)$deliverable['client_id']]);
foreach($q->fetchAll(PDO::FETCH_ASSOC)as$p)$personaSummary[(string)$p['id']]=$p;
?>
<aside class="panel field-wide" data-persona-summary hidden aria-live="polite"></aside>
<script type="application/json" data-persona-data><?= json_encode($personaSummary,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
<script>
(()=>{const select=document.querySelector('[name="persona_id"]'),box=document.querySelector('[data-persona-summary]');if(!select||!box)return;
const profiles=JSON.parse(document.querySelector('[data-persona-data]').textContent);
function show(){const p=profiles[select.value];box.replaceChildren();box.hidden=!p;if(!p)return;
for(const [key,label]of Object.entries({nom_persona:'Persona',profession:'Profil',objectif:'Objectif',probleme:'Besoin',freins:'Freins',canaux:'Canaux'})){if(!p[key])continue;const line=document.createElement('p');line.textContent=label+' : '+p[key];box.append(line);}}
select.addEventListener('change',show);show();})();
</script>
