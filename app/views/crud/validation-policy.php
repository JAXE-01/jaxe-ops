<?php $validationPolicy=ValidationPolicy::project((int)($record['id']??0),TenantGuard::tenantId()); ?>
<fieldset class="field field-wide">
 <legend>Validations du projet</legend>
 <input type="hidden" name="validation_policy_present" value="1">
 <label>Règles <select name="validation_mode"><option value="inherit" <?= !empty($validationPolicy['inherit'])?'selected':'' ?>>Hériter de l’entreprise</option><option value="custom" <?= empty($validationPolicy['inherit'])?'selected':'' ?>>Personnaliser ce projet</option></select></label>
 <label><input type="checkbox" name="validation_internal" value="1" <?= !empty($validationPolicy['internal'])?'checked':'' ?>> Validation interne obligatoire</label>
 <label><input type="checkbox" name="validation_client" value="1" <?= !empty($validationPolicy['client'])?'checked':'' ?>> Validation client obligatoire</label>
 <p class="mini-text">Les cases s’appliquent en mode personnalisé. Les nouveaux contenus prennent ces règles ; les contenus existants conservent leurs validations. L’approbation de publication et les droits restent obligatoires.</p>
</fieldset>
