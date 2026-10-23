-- Migración no destructiva: válida sobre la base de la versión 4.
ALTER TABLE users ADD COLUMN IF NOT EXISTS session_version INTEGER NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS login_attempts (
  fingerprint CHAR(64) PRIMARY KEY,
  failed_count INTEGER NOT NULL DEFAULT 0,
  locked_until TIMESTAMPTZ,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS login_attempts_updated_idx ON login_attempts(updated_at);

CREATE TABLE IF NOT EXISTS password_resets (
  user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  code_hash CHAR(64) NOT NULL,
  expires_at TIMESTAMPTZ NOT NULL,
  attempts INTEGER NOT NULL DEFAULT 0,
  requested_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS password_reset_requests (
  fingerprint CHAR(64) PRIMARY KEY,
  attempts INTEGER NOT NULL DEFAULT 0,
  window_started_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  last_requested_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGSERIAL PRIMARY KEY,
  occurred_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  actor_user_id INTEGER,
  db_user TEXT NOT NULL DEFAULT CURRENT_USER,
  source TEXT NOT NULL DEFAULT 'sql/terminal',
  table_name TEXT NOT NULL,
  record_id TEXT,
  operation TEXT NOT NULL,
  previous_data JSONB,
  new_data JSONB
);
CREATE INDEX IF NOT EXISTS idx_audit_time ON audit_logs(occurred_at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_table ON audit_logs(table_name,record_id);

-- Esta bitácora registra también los cambios efectuados directamente desde psql.
-- Nunca almacena hashes de contraseñas, códigos de recuperación ni contenido clínico.
CREATE OR REPLACE FUNCTION saludcita_audit_trigger() RETURNS TRIGGER AS $$
DECLARE
  before_row JSONB;
  after_row JSONB;
  record_key TEXT;
  actor_text TEXT;
BEGIN
  IF TG_OP <> 'INSERT' THEN before_row := to_jsonb(OLD); END IF;
  IF TG_OP <> 'DELETE' THEN after_row := to_jsonb(NEW); END IF;
  IF TG_TABLE_NAME = 'users' THEN
    before_row := before_row - 'password_hash' - 'session_version';
    after_row := after_row - 'password_hash' - 'session_version';
  ELSIF TG_TABLE_NAME = 'clinical_records' THEN
    before_row := before_row - 'symptoms' - 'diagnosis' - 'treatment' - 'notes';
    after_row := after_row - 'symptoms' - 'diagnosis' - 'treatment' - 'notes';
  END IF;
  record_key := COALESCE(after_row->>'id',before_row->>'id');
  IF TG_TABLE_NAME = 'users' THEN
    IF TG_OP = 'UPDATE' THEN
      IF OLD.password_hash IS DISTINCT FROM NEW.password_hash THEN
        after_row := after_row || jsonb_build_object('security_event','password_changed');
      END IF;
    END IF;
  END IF;
  IF TG_OP = 'UPDATE' AND before_row IS NOT DISTINCT FROM after_row THEN RETURN NEW; END IF;
  actor_text := NULLIF(current_setting('saludcita.actor_id',true),'');
  INSERT INTO audit_logs(actor_user_id,source,table_name,record_id,operation,previous_data,new_data)
    VALUES (CASE WHEN actor_text ~ '^[0-9]+$' THEN actor_text::INT ELSE NULL END,
            CASE WHEN actor_text IS NULL THEN 'sql/terminal' ELSE 'web' END,
            TG_TABLE_NAME,record_key,TG_OP,before_row,after_row);
  RETURN NULL;
END; $$ LANGUAGE plpgsql;

DO $$
DECLARE tab TEXT;
BEGIN
  FOREACH tab IN ARRAY ARRAY['users','patients','doctors','specialties','schedules','appointments','clinical_records','invoices','notifications'] LOOP
    EXECUTE format('DROP TRIGGER IF EXISTS tr_saludcita_audit ON %I',tab);
    EXECUTE format('CREATE TRIGGER tr_saludcita_audit AFTER INSERT OR UPDATE OR DELETE ON %I FOR EACH ROW EXECUTE FUNCTION saludcita_audit_trigger()',tab);
  END LOOP;
END $$;
