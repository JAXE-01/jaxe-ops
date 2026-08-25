<?php
class CrudModel extends Model {
    private $config;
    private $recordCache = [];

    public function __construct(array $config) {
        parent::__construct();
        $this->config = $config;
    }

    public function getAll() {
        $table = $this->config['table'];
        $stmt = $this->db->query("SELECT * FROM {$table} ORDER BY " . $this->buildOrderClause());
        return TenantGuard::filterRows($table, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function filterRows(array $rows, array $filters, array $options = []) {
        $clientId = isset($filters['client_id']) && $filters['client_id'] !== '' ? (string) $filters['client_id'] : null;
        $search = trim((string) ($filters['q'] ?? ''));
        $roleFilter = trim((string) ($filters['role'] ?? ''));
        $secondaryRoleFilter = trim((string) ($filters['secondary_role'] ?? ''));
        $statusFilter = trim((string) ($filters['statut'] ?? ''));

        return array_values(array_filter($rows, function ($row) use ($clientId, $search, $options, $roleFilter, $secondaryRoleFilter, $statusFilter) {
            if ($clientId !== null) {
                $client = $this->resolveClientForRow($row, $this->config['clientGroup']['path'] ?? []);
                if ((string) ($client['id'] ?? '') !== $clientId) {
                    return false;
                }
            }

            if ($search === '') {
                if (($this->config['route'] ?? '') === 'user') {
                    if ($roleFilter !== '' && (string) ($row['role'] ?? '') !== $roleFilter) {
                        return false;
                    }
                    if ($secondaryRoleFilter !== '') {
                        $secondaryList = UserRoles::normalizeList((string) ($row['secondary_roles'] ?? ''));
                        if (!in_array($secondaryRoleFilter, $secondaryList, true)) {
                            return false;
                        }
                    }
                    if ($statusFilter !== '' && (string) ($row['statut'] ?? '') !== $statusFilter) {
                        return false;
                    }
                }

                return true;
            }

            $haystacks = [];
            foreach ($this->config['listFields'] as $field) {
                $haystacks[] = $this->resolveSearchValue($row, $field, $options);
            }

            foreach ($this->config['formFields'] as $field => $meta) {
                if (!in_array($field, $this->config['listFields'], true)) {
                    $haystacks[] = $this->resolveSearchValue($row, $field, $options);
                }
            }

            $normalizedSearch = $this->normalizeSearchText($search);
            $matchesSearch = false;
            foreach ($haystacks as $haystack) {
                if ($haystack !== '' && strpos($haystack, $normalizedSearch) !== false) {
                    $matchesSearch = true;
                    break;
                }
            }

            if (!$matchesSearch) {
                return false;
            }

            if (($this->config['route'] ?? '') === 'user') {
                if ($roleFilter !== '' && (string) ($row['role'] ?? '') !== $roleFilter) {
                    return false;
                }
                if ($secondaryRoleFilter !== '') {
                    $secondaryList = UserRoles::normalizeList((string) ($row['secondary_roles'] ?? ''));
                    if (!in_array($secondaryRoleFilter, $secondaryList, true)) {
                        return false;
                    }
                }
                if ($statusFilter !== '' && (string) ($row['statut'] ?? '') !== $statusFilter) {
                    return false;
                }
            }

            return true;
        }));
    }

    public function getClientFilterOptions(array $rows) {
        $groups = $this->getClientGroups($rows);
        $options = [];
        foreach ($groups as $group) {
            if ($group['id'] === null) {
                continue;
            }

            $options[(string) $group['id']] = [
                'id' => (string) $group['id'],
                'label' => $group['label'],
                'count' => count($group['rows'])
            ];
        }

        return $options;
    }

    public function getClientGroups(array $rows) {
        $path = $this->config['clientGroup']['path'] ?? [];
        if (empty($path)) {
            return [];
        }

        $groups = [];
        foreach ($rows as $row) {
            $client = $this->resolveClientForRow($row, $path);
            $groupKey = (string) ($client['id'] ?? 'none');

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'id' => $client['id'] ?? null,
                    'label' => $client['label'] ?? 'Sans client',
                    'rows' => []
                ];
            }

            $groups[$groupKey]['rows'][] = $row;
        }

        foreach ($groups as &$group) {
            usort($group['rows'], function ($left, $right) {
                $field = $this->config['titleField'] ?? $this->config['primaryKey'];
                return strcasecmp((string) ($left[$field] ?? ''), (string) ($right[$field] ?? ''));
            });
        }
        unset($group);

        uasort($groups, static function ($left, $right) {
            return strcasecmp($left['label'], $right['label']);
        });

        return array_values($groups);
    }

    public function getById($id) {
        $table = $this->config['table'];
        $primaryKey = $this->config['primaryKey'];
        $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE {$primaryKey} = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!TenantGuard::canAccessRecord($table, $record)) { return null; }
        if ($record) { AgencyAccessPolicy::auditAccess($table, (int) ($record[$primaryKey] ?? 0), AgencyAccessPolicy::defaultCapability($table), $record); }
        return $record;
    }

    public function create(array $data) {
        $payload = TenantGuard::prepareCreate($this->config['table'], $this->sanitize($data), $this->config);
        if (empty($payload)) {
            return false;
        }
        $columns = array_keys($payload);
        $placeholders = array_map(static function ($column) {
            return ':' . $column;
        }, $columns);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->config['table'],
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        $stmt = $this->db->prepare($sql);
        if (!$stmt->execute($payload)) {
            return false;
        }
        $createdId = (int) $this->db->lastInsertId();
        TenantGuard::afterCreate($this->config['table'], $createdId);
        return $createdId;
    }

    public function update($id, array $data) {
        $currentRecord=$this->getById($id);
        TenantGuard::assertRecord($this->config['table'], $currentRecord);
        AgencyAccessPolicy::assertRecordCapability($this->config['table'], $currentRecord, AgencyAccessPolicy::defaultCapability($this->config['table']), true);
        $payload = TenantGuard::prepareUpdate($this->config['table'], $this->sanitize($data), $this->config);
        $assignments = [];
        foreach (array_keys($payload) as $column) {
            $assignments[] = $column . ' = :' . $column;
        }
        $payload['current_id'] = $id;
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = :current_id',
            $this->config['table'],
            implode(', ', $assignments),
            $this->config['primaryKey']
        );
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($payload);
    }

    public function delete($id) {
        $currentRecord=$this->getById($id);
        TenantGuard::assertRecord($this->config['table'], $currentRecord);
        AgencyAccessPolicy::assertRecordCapability($this->config['table'], $currentRecord, AgencyAccessPolicy::defaultCapability($this->config['table']), true);
        $stmt = $this->db->prepare(
            sprintf('DELETE FROM %s WHERE %s = :id', $this->config['table'], $this->config['primaryKey'])
        );
        return $stmt->execute(['id' => $id]);
    }

    public function bulkDeleteByIds(array $ids, $primaryKey, $excludeId = 0) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $ids = array_values(array_filter($ids, function ($id) { return $this->getById($id) !== null; }));
        if (empty($ids)) {
            return 0;
        }

        $idParams = [];
        $bindings = [];
        foreach ($ids as $index => $id) {
            $paramName = ':id' . $index;
            $idParams[] = $paramName;
            $bindings['id' . $index] = $id;
        }

        $sql = sprintf('DELETE FROM %s WHERE %s IN (%s)', $this->config['table'], $primaryKey, implode(',', $idParams));
        if ((int) $excludeId > 0) {
            $sql .= sprintf(' AND %s <> :exclude_id', $primaryKey);
            $bindings['exclude_id'] = (int) $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        return (int) $stmt->rowCount();
    }

    public function bulkUpdateByIds(array $ids, $primaryKey, array $fields, $excludeId = 0) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids) || empty($fields)) {
            return 0;
        }

        $setParts = [];
        $bindings = [];
        foreach ($fields as $column => $value) {
            $setParts[] = $column . ' = :' . $column;
            $bindings[$column] = $value;
        }

        $idParams = [];
        foreach ($ids as $index => $id) {
            $paramName = ':id' . $index;
            $idParams[] = $paramName;
            $bindings['id' . $index] = $id;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s IN (%s)',
            $this->config['table'],
            implode(', ', $setParts),
            $primaryKey,
            implode(',', $idParams)
        );

        if ((int) $excludeId > 0) {
            $sql .= sprintf(' AND %s <> :exclude_id', $primaryKey);
            $bindings['exclude_id'] = (int) $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        return (int) $stmt->rowCount();
    }

    public function countAll() {
        return count($this->getAll());
    }

    public function getRelationOptions(string $moduleKey) {
        $module = ModuleRegistry::get($moduleKey);
        if ($module === null) {
            return [];
        }

        $labelField = $module['titleField'];
        $stmt = $this->db->query(sprintf(
            'SELECT * FROM %s ORDER BY %s ASC',
            $module['table'],
            $labelField
        ));

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $rows = TenantGuard::filterRows($module['table'], $rows);
        $options = [];
        foreach ($rows as $row) { $options[$row[$module['primaryKey']]] = $row[$labelField]; }
        return $options;
    }

    public function getConfig() {
        return $this->config;
    }

    private function buildOrderClause() {
        if (!empty($this->config['listOrder']) && is_array($this->config['listOrder'])) {
            $chunks = [];
            foreach ($this->config['listOrder'] as $column => $direction) {
                $normalizedDirection = strtoupper((string) $direction) === 'ASC' ? 'ASC' : 'DESC';
                $chunks[] = $column . ' ' . $normalizedDirection;
            }

            if (!empty($chunks)) {
                return implode(', ', $chunks);
            }
        }

        return $this->config['primaryKey'] . ' DESC';
    }

    private function resolveSearchValue(array $row, $field, array $options) {
        $value = $row[$field] ?? '';
        $meta = $this->config['formFields'][$field] ?? [];

        if (($meta['type'] ?? null) === 'relation') {
            $value = $options[$field][$value] ?? $value;
        }

        if ($value === null) {
            return '';
        }

        if (!is_scalar($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $this->normalizeSearchText((string) $value);
    }

    private function normalizeSearchText($value) {
        $text = mb_strtolower(trim((string) $value), 'UTF-8');
        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }

        return $text;
    }

    private function resolveClientForRow(array $row, array $path) {
        $currentRow = $row;
        $lastIndex = count($path) - 1;

        foreach ($path as $index => $step) {
            $field = $step['field'];
            $moduleKey = $step['module'];
            $relatedId = $currentRow[$field] ?? null;

            if (empty($relatedId)) {
                return ['id' => null, 'label' => 'Sans client'];
            }

            $relatedRow = $this->getRelatedRecord($moduleKey, $relatedId);
            if ($relatedRow === null) {
                return ['id' => null, 'label' => 'Sans client'];
            }

            if ($index === $lastIndex) {
                $module = ModuleRegistry::get($moduleKey);
                $titleField = $module['titleField'] ?? $module['primaryKey'];
                return [
                    'id' => $relatedRow[$module['primaryKey']] ?? $relatedId,
                    'label' => (string) ($relatedRow[$titleField] ?? 'Sans client')
                ];
            }

            $currentRow = $relatedRow;
        }

        return ['id' => null, 'label' => 'Sans client'];
    }

    private function getRelatedRecord($moduleKey, $id) {
        if (isset($this->recordCache[$moduleKey][$id])) {
            return $this->recordCache[$moduleKey][$id];
        }

        $module = ModuleRegistry::get($moduleKey);
        if ($module === null) {
            return null;
        }

        $stmt = $this->db->prepare(sprintf(
            'SELECT * FROM %s WHERE %s = :id LIMIT 1',
            $module['table'],
            $module['primaryKey']
        ));
        $stmt->execute(['id' => $id]);

        $record = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $this->recordCache[$moduleKey][$id] = $record;

        return $record;
    }

    private function sanitize(array $data) {
        $payload = [];
        foreach ($this->config['formFields'] as $field => $meta) {
            if (($meta['type'] ?? null) === 'checkbox' && !array_key_exists($field, $data)) {
                $payload[$field] = 0;
                continue;
            }

            if (!array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            $type = $meta['type'] ?? 'text';

            if ($type === 'relation' && $value === '') {
                $payload[$field] = null;
                continue;
            }

            if ($type === 'json') {
                $payload[$field] = $this->normalizeJsonField($field, $value, !empty($meta['nullable']));
                continue;
            }

            if ($type === 'multiselect') {
                $payload[$field] = UserRoles::serialize($value);
                continue;
            }

            if (in_array($type, ['file', 'files'], true)) {
                if ($value === null || $value === '' || $value === []) {
                    $payload[$field] = null;
                } else {
                    $payload[$field] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                continue;
            }

            if ($type === 'checkbox') {
                $payload[$field] = empty($value) ? 0 : 1;
                continue;
            }

            if (in_array($type, ['number', 'relation'], true)) {
                $payload[$field] = $value === '' ? null : $value;
                continue;
            }

            if ($type === 'password') {
                $trimmed = trim((string) $value);
                $payload[$field] = $trimmed === '' ? null : password_hash($trimmed, PASSWORD_BCRYPT);
                continue;
            }

            $payload[$field] = is_string($value) ? trim($value) : $value;
        }
        return $payload;
    }

    private function normalizeJsonField($field, $value, $nullable) {
        if (!is_string($value)) {
            return $nullable ? null : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return $nullable ? null : '{}';
        }

        json_decode($trimmed, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException(sprintf('Le champ "%s" doit contenir un JSON valide.', $field));
        }

        return $trimmed;
    }
}
