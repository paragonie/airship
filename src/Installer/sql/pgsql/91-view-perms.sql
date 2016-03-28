CREATE OR REPLACE VIEW view_perm_contexts AS
    SELECT
        c.*,
        COUNT(r.ruleid) AS numRules
    FROM 
        airship_perm_contexts c
    LEFT JOIN
        airship_perm_rules r
        ON r.context = c.contextid
    GROUP BY c.contextid;