-- Identidades Google explícitamente vinculadas desde una cuenta autenticada.
-- Ni el email de Google ni un OAuth nuevo dan permisos administrativos.
CREATE TABLE IF NOT EXISTS oauth_identities (
 id BIGSERIAL PRIMARY KEY,
 provider VARCHAR(20) NOT NULL CHECK(provider IN ('google')),
 provider_sub VARCHAR(255) NOT NULL,
 user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 linked_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
 UNIQUE(provider, provider_sub),
 UNIQUE(provider, user_id)
);
CREATE INDEX IF NOT EXISTS oauth_identities_user_idx ON oauth_identities(user_id);
