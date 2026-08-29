<?php $validationDefaults=ValidationPolicy::defaults(TenantGuard::tenantId()); ?>
<fieldset class="field field-wide">
 <legend>Validations par défaut de cette entreprise</legend>
 <input type="hidden" name="validation_policy_present" value="1">
 <label><input type="checkbox" name="validation_internal" value="1" <?= !empty($validationDefaults['internal'])?'checked':'' ?>> Validation interne obligatoire</label>
 <label><input type="checkbox" name="validation_client" value="1" <?= !empty($validationDefaults['client'])?'checked':'' ?>> Validation client obligatoire</label>
 <p class="mini-text">Applicables aux nouveaux contenus des projets héritant de ces règles. Aucun contenu existant n’est automatiquement validé.</p>
</fieldset>
