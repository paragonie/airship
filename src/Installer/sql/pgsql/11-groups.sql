CREATE TABLE airship_groups (
    groupid BIGSERIAL PRIMARY KEY,
    name TEXT,
    superuser BOOLEAN DEFAULT FALSE,
    inherits INTEGER NULL,
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW()
);
DROP TRIGGER IF EXISTS update_airship_groups_modtime ON airship_groups;
CREATE TRIGGER update_airship_groups_modtime
    BEFORE UPDATE ON airship_groups
    FOR EACH ROW EXECUTE PROCEDURE update_modtime();

CREATE TABLE airship_users_groups (
    userid INTEGER NOT NULL,
    groupid INTEGER NOT NULL,
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW()
);
DROP TRIGGER IF EXISTS update_airship_users_groups_modtime ON airship_users_groups;
CREATE TRIGGER update_airship_users_groups_modtime
    BEFORE UPDATE ON airship_users_groups
    FOR EACH ROW EXECUTE PROCEDURE update_modtime();

CREATE OR REPLACE FUNCTION group_ancestors(child BIGINT)
RETURNS TABLE (parent BIGINT) AS $$
    WITH RECURSIVE parents AS (
        (
            SELECT 
                node.groupid,
                node.inherits
            FROM airship_groups AS node
            WHERE 
                node.groupid = $1
        )
        UNION ALL
        (
            SELECT 
                g.groupid,
                g.inherits
            FROM airship_groups g
            JOIN parents p
                ON p.inherits = g.groupid
        )
    )
    SELECT $1
    UNION
    SELECT groupid
    FROM parents;
$$ language 'sql';

-- Get all of a users' group memberships

CREATE OR REPLACE FUNCTION memberOf(user_id BIGINT)
RETURNS TABLE (groupid BIGINT) AS $$
    SELECT DISTINCT airship_groups.groupid
    FROM airship_groups
    LEFT JOIN airship_users_groups 
        ON airship_groups.groupid = airship_users_groups.groupid
    LEFT JOIN airship_users 
        ON airship_users_groups.userid = airship_users.userid
    WHERE airship_users.userid = $1
    UNION
    SELECT DISTINCT group_ancestors(g.groupid)
    FROM (
        SELECT airship_groups.groupid
        FROM airship_groups
        LEFT JOIN airship_users_groups 
            ON airship_groups.groupid = airship_users_groups.groupid
        LEFT JOIN airship_users 
            ON airship_users_groups.userid = airship_users.userid
        WHERE airship_users.userid = $1
    ) g;
$$ language 'sql';
