CREATE TABLE IF NOT EXISTS airship_auth_tokens (
    tokenid BIGSERIAL PRIMARY KEY,
    userid INTEGER,
    selector TEXT,
    validator TEXT,
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW(),
    FOREIGN KEY (userid) REFERENCES airship_users (userid)
);
CREATE UNIQUE INDEX ON airship_auth_tokens (selector);

DROP TRIGGER IF EXISTS update_airship_auth_tokens_modtime ON airship_auth_tokens;
CREATE TRIGGER update_airship_auth_tokens_modtime
    BEFORE UPDATE ON airship_auth_tokens
    FOR EACH ROW EXECUTE PROCEDURE update_modtime();
