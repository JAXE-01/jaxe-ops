ALTER TABLE tenant_memberships ADD UNIQUE INDEX IF NOT EXISTS uq_membership_tenant_user (tenant_id, user_id);
