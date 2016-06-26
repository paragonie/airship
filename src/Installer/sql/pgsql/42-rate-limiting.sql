CREATE TYPE type_airship_account_failure AS ENUM ('login', 'recovery');

CREATE TABLE airship_failed_logins (
    failureid BIGSERIAL PRIMARY KEY,
    username TEXT,
    ipaddress TEXT,
    action type_airship_account_failure,
    subnet TEXT,
    sealed_password TEXT,
    occurred TIMESTAMP DEFAULT NOW()
);

CREATE INDEX ON airship_failed_logins (username);
CREATE INDEX ON airship_failed_logins (ipaddress);
CREATE INDEX ON airship_failed_logins (action);
CREATE INDEX ON airship_failed_logins (subnet);
CREATE INDEX ON airship_failed_logins (username, subnet);
CREATE INDEX ON airship_failed_logins (username, action);
CREATE INDEX ON airship_failed_logins (subnet, action);
CREATE INDEX ON airship_failed_logins (username, subnet, action);
