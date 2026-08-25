<?php
function field_value(array $record, string $field) {
    return $record[$field] ?? '';
}

function field_is_visible(array $meta, array $record) {
    if (empty($meta['showWhen']['field']) || !isset($meta['showWhen']['values'])) {
        return true;
    }

    $controllerField = $meta['showWhen']['field'];
    $expectedValues = array_map('strval', (array) $meta['showWhen']['values']);
    return in_array((string) ($record[$controllerField] ?? ''), $expectedValues, true);
}

function form_has_files(array $fields) {
    foreach ($fields as $meta) {
        if (in_array($meta['type'] ?? 'text', ['file', 'files'], true)) {
            return true;
        }
    }

    return false;
}

function field_multiselect_values($value) {
    if (is_array($value)) {
        return array_map('strval', $value);
    }

    return array_values(array_filter(array_map('trim', explode(',', (string) $value)), static function ($item) {
        return $item !== '';
    }));
}
?>
<section class="page-intro-card compact-intro crud-form-intro"><div><span class="page-eyebrow">Gestion <?= htmlspecialchars(strtolower($module['label'])) ?></span><h2><?= !empty($record) ? 'Modifier' : 'Créer' ?> · <?= htmlspecialchars($module['label']) ?></h2><p>Renseignez les informations nécessaires puis enregistrez. Les champs conditionnels apparaissent selon vos choix.</p></div><a class="button secondary" href="<?= htmlspecialchars($returnTo ?? route_url('/' . $module['route'])) ?>">← Retour</a></section><section class="panel crud-form-panel">
    <?php if (!empty($formHint)): ?>
        <div class="info-banner"><?= htmlspecialchars($formHint) ?></div>
    <?php endif; ?>
    <form method="post" class="form-grid two-columns crud-entity-form" <?= form_has_files($module['formFields']) ? 'enctype="multipart/form-data"' : '' ?>>
        <?php if (!empty($returnTo)): ?>
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">
        <?php endif; ?>
        <?php foreach ($module['formFields'] as $field => $meta): ?>
            <?php $value = field_value($record, $field); ?>
            <?php $isVisible = field_is_visible($meta, $record); ?>
            <?php $showWhenField = $meta['showWhen']['field'] ?? ''; ?>
            <?php $showWhenValues = !empty($meta['showWhen']['values']) ? implode('|', (array) $meta['showWhen']['values']) : ''; ?>
            <label class="field <?= in_array(($meta['type'] ?? 'text'), ['textarea', 'files', 'multiselect'], true) ? 'field-wide' : '' ?> <?= $isVisible ? '' : 'is-hidden' ?>" <?= $showWhenField !== '' ? 'data-show-when-field="' . htmlspecialchars($showWhenField) . '" data-show-when-values="' . htmlspecialchars($showWhenValues) . '"' : '' ?>>
                <span><?= htmlspecialchars($meta['label']) ?></span>
                <?php if (($meta['type'] ?? 'text') === 'textarea'): ?>
                    <textarea name="<?= htmlspecialchars($field) ?>" <?= !empty($meta['required']) ? 'required' : '' ?> <?= !$isVisible ? 'disabled' : '' ?>><?= htmlspecialchars((string) $value) ?></textarea>
                <?php elseif (($meta['type'] ?? 'text') === 'checkbox'): ?>
                    <input type="checkbox" name="<?= htmlspecialchars($field) ?>" value="1" <?= !empty($value) ? 'checked' : '' ?> <?= !$isVisible ? 'disabled' : '' ?>>
                <?php elseif (($meta['type'] ?? 'text') === 'select'): ?>
                    <select name="<?= htmlspecialchars($field) ?>" <?= !empty($meta['required']) ? 'required' : '' ?> <?= !$isVisible ? 'disabled' : '' ?>>
                        <?php foreach ($meta['options'] as $optionValue => $label): ?>
                            <option value="<?= htmlspecialchars($optionValue) ?>" <?= (string) $value === (string) $optionValue ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif (($meta['type'] ?? 'text') === 'multiselect'): ?>
                    <?php $selectedValues = field_multiselect_values($value); ?>
                    <select name="<?= htmlspecialchars($field) ?>[]" multiple size="<?= max(4, min(8, count($meta['options'] ?? []))) ?>" <?= !$isVisible ? 'disabled' : '' ?>>
                        <?php foreach ($meta['options'] as $optionValue => $label): ?>
                            <option value="<?= htmlspecialchars($optionValue) ?>" <?= in_array((string) $optionValue, $selectedValues, true) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="field-help">Maintiens Ctrl ou Cmd pour selectionner plusieurs roles.</small>
                <?php elseif (($meta['type'] ?? 'text') === 'relation'): ?>
                    <select name="<?= htmlspecialchars($field) ?>" <?= empty($meta['nullable']) ? 'required' : '' ?> <?= !$isVisible ? 'disabled' : '' ?>>
                        <option value="">Selectionner</option>
                        <?php foreach (($options[$field] ?? []) as $optionValue => $label): ?>
                            <option value="<?= htmlspecialchars((string) $optionValue) ?>" <?= (string) $value === (string) $optionValue ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif (($meta['type'] ?? 'text') === 'files'): ?>
                    <input type="file" name="<?= htmlspecialchars($field) ?>[]" multiple <?= !empty($meta['accept']) ? 'accept="' . htmlspecialchars($meta['accept']) . '"' : '' ?> <?= !$isVisible ? 'disabled' : '' ?>>
                    <?php if (!empty($meta['hint'])): ?>
                        <small class="field-help"><?= htmlspecialchars($meta['hint']) ?></small>
                    <?php endif; ?>
                <?php elseif (($meta['type'] ?? 'text') === 'file'): ?>
                    <input type="file" name="<?= htmlspecialchars($field) ?>" <?= !empty($meta['accept']) ? 'accept="' . htmlspecialchars($meta['accept']) . '"' : '' ?> <?= !$isVisible ? 'disabled' : '' ?>>
                    <?php if (!empty($meta['hint'])): ?>
                        <small class="field-help"><?= htmlspecialchars($meta['hint']) ?></small>
                    <?php endif; ?>
                <?php else: ?>
                    <?php
                    $inputType = (string) ($meta['type'] ?? 'text');
                    $inputValue = (string) $value;
                    if ($inputType === 'password') {
                        $inputValue = '';
                    }
                    ?>
                    <input
                        type="<?= htmlspecialchars($inputType) ?>"
                        name="<?= htmlspecialchars($field) ?>"
                        value="<?= htmlspecialchars($inputValue) ?>"
                        <?= isset($meta['step']) ? 'step="' . htmlspecialchars($meta['step']) . '"' : '' ?>
                        <?= !empty($meta['required']) ? 'required' : '' ?>
                        <?= $inputType === 'password' ? 'autocomplete="new-password"' : '' ?>
                        <?= !$isVisible ? 'disabled' : '' ?>
                    >
                <?php endif; ?>
            </label>
        <?php endforeach; ?>

        <div class="form-actions crud-form-actions">
            <button class="button primary" type="submit">Enregistrer</button>
            <a class="button secondary" href="<?= htmlspecialchars($returnTo ?? route_url('/' . $module['route'])) ?>"><?= htmlspecialchars($backLabel ?? 'Annuler') ?></a>
        </div>
    </form>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var conditionalFields = document.querySelectorAll('[data-show-when-field]');
    if (!conditionalFields.length) {
        return;
    }

    function updateConditionalField(field) {
        var controllerName = field.getAttribute('data-show-when-field');
        var allowedValues = (field.getAttribute('data-show-when-values') || '').split('|');
        var controller = document.querySelector('[name="' + controllerName + '"]');
        if (!controller) {
            return;
        }

        var currentValue = controller.type === 'checkbox' ? (controller.checked ? '1' : '0') : controller.value;
        var isVisible = allowedValues.indexOf(currentValue) !== -1;
        field.classList.toggle('is-hidden', !isVisible);
        field.querySelectorAll('input, select, textarea').forEach(function (input) {
            input.disabled = !isVisible;
        });
    }

    conditionalFields.forEach(function (field) {
        var controllerName = field.getAttribute('data-show-when-field');
        var controller = document.querySelector('[name="' + controllerName + '"]');
        if (!controller) {
            return;
        }
        updateConditionalField(field);
        controller.addEventListener('change', function () {
            conditionalFields.forEach(updateConditionalField);
        });
    });
});
</script>
