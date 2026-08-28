ALTER TABLE projets ADD COLUMN publication_rules LONGTEXT NULL;
ALTER TABLE abonnements ADD COLUMN tenant_id INT NULL;
UPDATE abonnements SET tenant_id=(SELECT id FROM tenants WHERE slug='jaxe-ops' LIMIT 1) WHERE tenant_id IS NULL;
