CREATE TABLE IF NOT EXISTS schema_migrations (
  version text PRIMARY KEY,
  applied_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS oauth_attempts (
  state_hash text PRIMARY KEY,
  browser_session_hash text NOT NULL,
  code_verifier_ciphertext text NOT NULL,
  redirect_uri text NOT NULL,
  expires_at timestamptz NOT NULL,
  consumed_at timestamptz,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS installations (
  id uuid PRIMARY KEY,
  restaurant_id bigint NOT NULL,
  fantasy_name text NOT NULL,
  granted_scopes text[] NOT NULL,
  status text NOT NULL CHECK (status IN ('active', 'reconnect_required', 'revoked')),
  access_token_ciphertext text NOT NULL,
  access_expires_at timestamptz NOT NULL,
  refresh_token_ciphertext text NOT NULL,
  token_version integer NOT NULL DEFAULT 1,
  last_sync_at timestamptz,
  last_error_code text,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE (restaurant_id)
);

CREATE TABLE IF NOT EXISTS app_sessions (
  session_hash text PRIMARY KEY,
  installation_id uuid NOT NULL REFERENCES installations(id) ON DELETE CASCADE,
  csrf_hash text NOT NULL,
  expires_at timestamptz NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS app_sessions_installation_idx ON app_sessions(installation_id);

CREATE TABLE IF NOT EXISTS sync_jobs (
  id bigserial PRIMARY KEY,
  installation_id uuid NOT NULL REFERENCES installations(id) ON DELETE CASCADE,
  status text NOT NULL CHECK (status IN ('pending', 'running', 'done', 'failed', 'cancelled')),
  attempts integer NOT NULL DEFAULT 0,
  run_after timestamptz NOT NULL DEFAULT now(),
  locked_at timestamptz,
  locked_by text,
  last_error_code text,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS sync_jobs_poll_idx ON sync_jobs(status, run_after);

CREATE TABLE IF NOT EXISTS metric_snapshots (
  id uuid PRIMARY KEY,
  installation_id uuid NOT NULL REFERENCES installations(id) ON DELETE CASCADE,
  period_start timestamptz NOT NULL,
  period_end timestamptz NOT NULL,
  metrics jsonb NOT NULL,
  evidence jsonb NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS metric_snapshots_tenant_idx ON metric_snapshots(installation_id, created_at DESC);

CREATE TABLE IF NOT EXISTS alerts (
  id uuid PRIMARY KEY,
  installation_id uuid NOT NULL REFERENCES installations(id) ON DELETE CASCADE,
  metric_snapshot_id uuid NOT NULL REFERENCES metric_snapshots(id) ON DELETE CASCADE,
  alert_type text NOT NULL,
  severity text NOT NULL CHECK (severity IN ('info', 'low', 'medium', 'high')),
  quality text NOT NULL CHECK (quality IN ('exact', 'mapped', 'proxy', 'insufficient')),
  title text NOT NULL,
  explanation text NOT NULL,
  action text NOT NULL,
  metric jsonb NOT NULL,
  evidence_refs jsonb NOT NULL,
  status text NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'acknowledged', 'dismissed')),
  created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS alerts_tenant_idx ON alerts(installation_id, created_at DESC);

CREATE TABLE IF NOT EXISTS alert_feedback (
  id uuid PRIMARY KEY,
  installation_id uuid NOT NULL REFERENCES installations(id) ON DELETE CASCADE,
  alert_id uuid NOT NULL REFERENCES alerts(id) ON DELETE CASCADE,
  useful boolean NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS audit_events (
  id bigserial PRIMARY KEY,
  installation_id uuid REFERENCES installations(id) ON DELETE SET NULL,
  event_type text NOT NULL,
  correlation_id text,
  metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
  created_at timestamptz NOT NULL DEFAULT now()
);
