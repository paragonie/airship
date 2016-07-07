CREATE TABLE IF NOT EXISTS airship_users (
    userid BIGSERIAL PRIMARY KEY,
    username TEXT,
    password TEXT,
    totp_secret TEXT,
    enable_2factor BOOLEAN DEFAULT FALSE,
    session_canary TEXT,
    display_name TEXT,
    email TEXT,
    uniqueid TEXT,
    real_name TEXT DEFAULT NULL,
    birthdate TIMESTAMP NULL,
    allow_reset BOOLEAN DEFAULT FALSE,
    gpg_public_key TEXT,
    custom_fields JSONB NULL,
    publicprofile BOOLEAN DEFAULT FALSE,
    superuser BOOLEAN DEFAULT FALSE,
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW()
);
CREATE UNIQUE INDEX IF NOT EXISTS ON airship_users (username);
CREATE UNIQUE INDEX IF NOT EXISTS ON airship_users (uniqueid);

DROP TRIGGER IF EXISTS update_airship_users_modtime ON airship_users;
CREATE TRIGGER update_airship_users_modtime
    BEFORE UPDATE ON airship_users
    FOR EACH ROW EXECUTE PROCEDURE update_modtime();
