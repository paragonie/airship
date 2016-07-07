CREATE TYPE type_airship_account_failure AS ENUM ('login', 'recovery');

CREATE TABLE IF NOT EXISTS airship_failed_logins (
    failureid BIGSERIAL PRIMARY KEY,
    username TEXT,
    ipaddress TEXT,
    action type_airship_account_failure,
    subnet TEXT,
    sealed_password TEXT,
    occurred TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS ON airship_failed_logins (username);
CREATE INDEX IF NOT EXISTS ON airship_failed_logins (ipaddress);
CREATE INDEX IF NOT EXISTS ON airship_failed_logins (action);
CREATE INDEX IF NOT EXISTS ON airship_failed_logins (subnet);
CREATE INDEX IF NOT EXISTS ON airship_failed_logins (username, subnet);
CREATE INDEX IF NOT EXISTS ON airship_failed_logins (username, action);
CREATE INDEX IF NOT EXISTS ON airship_failed_logins (subnet, action);
CREATE INDEX IF NOT EXISTS ON airship_failed_logins (username, subnet, action);
