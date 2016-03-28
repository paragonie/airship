CREATE TABLE airship_perm_actions (
    actionid BIGSERIAL PRIMARY KEY,
    cabin TEXT NOT NULL,
    label TEXT NOT NULL,
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW(),
    UNIQUE(cabin, label)
);

CREATE TABLE airship_perm_contexts (
    contextid BIGSERIAL PRIMARY KEY,
    cabin TEXT NOT NULL,
    locator TEXT,
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW(),
    UNIQUE(cabin, locator)
);

CREATE TABLE airship_perm_rules (
    ruleid BIGSERIAL PRIMARY KEY,
    context INTEGER NOT NULL,
    action INTEGER NOT NULL,
    userid INTEGER NULL,
    groupid INTEGER NULL,
    CHECK((userid IS NULL) != (groupid IS NULL)),
    FOREIGN KEY(context) REFERENCES airship_perm_contexts (contextid),
    FOREIGN KEY(action) REFERENCES airship_perm_actions (actionid)
);

CREATE INDEX airship_perm_actions_label_idx ON airship_perm_actions (label);
DROP TRIGGER IF EXISTS update_airship_perm_actions_modtime ON airship_perm_actions;
CREATE TRIGGER update_airship_perm_actions_modtime
    BEFORE UPDATE ON airship_perm_actions
    FOR EACH ROW EXECUTE PROCEDURE update_modtime();

DROP TRIGGER IF EXISTS update_airship_perm_contexts_modtime ON airship_perm_contexts;
CREATE TRIGGER update_airship_perm_contexts_modtime
    BEFORE UPDATE ON airship_perm_contexts
    FOR EACH ROW EXECUTE PROCEDURE update_modtime();

DROP TRIGGER IF EXISTS update_airship_perm_rules_modtime ON airship_perm_rules;
CREATE TRIGGER update_airship_perm_rules_modtime
    BEFORE UPDATE ON airship_perm_rules
    FOR EACH ROW EXECUTE PROCEDURE update_modtime();
