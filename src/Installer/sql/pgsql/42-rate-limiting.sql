CREATE TABLE airship_failed_logins (
    failureid BIGSERIAL PRIMARY KEY,
    username TEXT,
    ipaddress TEXT,
    sealed_password TEXT,
    occurred TIMESTAMP DEFAULT NOW()
);

CREATE INDEX ON airship_failed_logins (username);
CREATE INDEX ON airship_failed_logins (ipaddress);
CREATE INDEX ON airship_failed_logins (username, ipaddress);
