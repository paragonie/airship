CREATE TABLE airship_user_preferences (
    preferenceid BIGSERIAL PRIMARY KEY,
    userid BIGINT REFERENCES airship_users (userid),
    preferences JSONB NULL,
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW()
);

DROP TRIGGER IF EXISTS update_airship_user_preferences_modtime ON airship_user_preferences;
CREATE TRIGGER update_airship_user_preferences_modtime
    BEFORE UPDATE ON airship_user_preferences
    FOR EACH ROW EXECUTE PROCEDURE update_modtime();