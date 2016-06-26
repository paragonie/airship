INSERT INTO airship_groups (groupid, name, superuser, inherits) VALUES 
    (1, 'Guest', FALSE, NULL),
    (2, 'Registered User', FALSE, NULL),
        (3, 'Trusted Commenter', FALSE, 2),
        (4, 'Writer', FALSE, 2),
        (5, 'Publisher', FALSE, 2),
        (6, 'Moderator', FALSE, 2),
            (7, 'Admin', TRUE, 6);

INSERT INTO airship_perm_actions (actionid, cabin, label) VALUES
    (1, 'Bridge', 'read'),
        (2, 'Bridge', 'create'),
        (3, 'Bridge', 'update'),
        (4, 'Bridge', 'delete'),
        (5, 'Bridge', 'publish'),
        (6, 'Bridge', 'index'),
    (7, 'Hull', 'read'),
        (8, 'Hull', 'create'),
        (9, 'Hull', 'update'),
        (10, 'Hull', 'publish');

INSERT INTO airship_perm_contexts (contextid, cabin, locator) VALUES
    ( 1, 'Hull', 'blog'),
    ( 2, 'Bridge', 'user'),
    ( 3, 'Bridge', 'blog/post'),
    ( 4, 'Bridge', 'blog/series'),
    ( 5, 'Bridge', 'blog/category'),
    ( 6, 'Bridge', 'blog/tag'),
    ( 7, 'Bridge', 'author'),
    ( 8, 'Bridge', 'pages'),
    ( 9, 'Bridge', 'cabins'),
    (10, 'Bridge', 'gadgets'),
    (11, 'Bridge', 'gears'),
    (12, 'Bridge', 'crew'),
    (13, 'Bridge', 'crew/groups'),
    (14, 'Bridge', 'crew/permissions'),
    (15, 'Bridge', 'crew/users'),
    (16, 'Bridge', 'my'),
    (17, 'Bridge', 'ajax/rich_text_preview'),
    (18, 'Bridge', 'ajax/authors_blog_posts'),
    (19, 'Bridge', 'ajax/authors_blog_series'),
    (20, 'Hull', '/'),
    (21, 'Bridge', 'blog'),
    (22, 'Bridge', 'cabin/([^/]+)'),
    (23, 'Bridge', 'cabins/manage')
    (24, 'Bridge', 'announcement/dismiss');

INSERT INTO airship_perm_rules (context, groupid, action) VALUES
    (1, 1, 7),
        (1, 2, 7),
        (1, 2, 8),
        (1, 3, 10),
        (1, 5, 9),
        (1, 5, 10),
    (2, 2, 1),
        (2, 1, 2),
        (2, 6, 3),
        (2, 6, 6),
    (3, 2, 1),
        (3, 4, 2),
        (3, 5, 5),
        (3, 6, 2),
        (3, 6, 3),
        (3, 6, 4),
        (3, 6, 5),
        (3, 6, 6),
    (4, 2, 1),
        (4, 2, 6),
        (4, 4, 2),
        (4, 5, 2),
        (4, 6, 2),
        (4, 4, 3),
        (4, 5, 3),
        (4, 6, 3),
        (4, 6, 4),
    (5, 2, 1),
        (5, 2, 1),
        (5, 2, 6),
        (5, 6, 2),
        (5, 6, 3),
        (5, 6, 4),
    (6, 2, 1),
        (6, 2, 6),
        (6, 6, 2),
        (6, 6, 3),
        (6, 6, 4),
    (7, 2, 1),
        (7, 6, 2),
        (7, 6, 3),
        (7, 6, 4),
    (8, 5, 1),
        (8, 5, 2),
        (8, 5, 3),
        (8, 5, 4),
        (8, 5, 5),
        (8, 5, 6),
        (8, 6, 1),
        (8, 6, 2),
        (8, 6, 3),
        (8, 6, 4),
        (8, 6, 5),
        (8, 6, 6),
    (9, 2, 1),
    (16, 2, 1),
        (16, 2, 2),
        (16, 2, 3),
        (16, 2, 4),
        (16, 2, 5),
        (16, 2, 6),
    (17, 2, 1),
        (17, 2, 2),
        (17, 2, 3),
        (17, 2, 4),
        (17, 2, 5),
        (17, 2, 6),
    (18, 2, 1),
        (18, 2, 2),
        (18, 2, 3),
        (18, 2, 4),
        (18, 2, 5),
        (18, 2, 6),
    (19, 2, 1),
        (19, 2, 2),
        (19, 2, 3),
        (19, 2, 4),
        (19, 2, 5),
        (19, 2, 6),
    (20, 1, 7),
        (20, 2, 7),
        (20, 2, 8),
        (20, 3, 10),
        (20, 5, 9),
        (20, 5, 10),
    (21, 2, 1),
    (22, 2, 1),
    (23, 6, 1),
    (24, 2, 1);

INSERT INTO hull_blog_authors (authorid, name, slug) VALUES
    (1, 'Captain', 'captain');

INSERT INTO hull_blog_author_owners (userid, authorid, default_author_for_user, in_charge) VALUES
    (1, 1, TRUE, TRUE);

INSERT INTO hull_blog_posts (postid, title, slug, shorturl, description, format, author, status) VALUES (
    1,
    'Hello World',
    'hello-world',
    'helloworld',
    'Welcome to your Airship',
    'Markdown',
    1,
    TRUE
);

INSERT INTO hull_blog_post_versions (post, body, format, live, published_by) VALUES (
    1,
    'Hello, world!

Click "Go to the Bridge" at the bottom to take control of your Airship.',
    'Markdown',
    TRUE,
    1
);

-- GENESIS BLOCKS:
INSERT INTO airship_tree_updates (channel, channelupdateid, data, merkleroot) VALUES
(
    'paragonie',
    1,
    '{"action":"CREATE","date_generated":"2016-06-04T16:00:00","public_key":"1d9b44a5ec7be970dcb07efa81e661cb493f700953c0c26e5161b9cf0637e7f1","supplier":"pragonie","type":"master","master":null}',
    '99b4556c9506fd1742ca837e534553c9dcff5cdfae3ef57c74eb6175c6c8ffb9da04102a6a83c5139efd83c5e6f52cabc557ed0726652e041e214b8a677247ea'
),
(
    'paragonie',
    2,
    '{"action":"CREATE","date_generated":"2016-06-04T16:05:00","public_key":"6731558f53c6edf15c7cc1e439b15c18d6dfc1fd2c66f9fda8c56cfe7d37110b","supplier":"pragonie","type":"signing","master":"{\"public_key\":\"1d9b44a5ec7be970dcb07efa81e661cb493f700953c0c26e5161b9cf0637e7f1\",\"signature\":\"017bb2dbe6fa75d3240f330be532bf8d9aced0654f257b5670edbd44c52f892459b5b314f095cd1df65346035a4b927dd4edbcfee677d4ebd5f861d6789fc301\"}"}',
    '940c0456c19d3606b27c89d15a82523f8fdb83928b4d27e027058a279665b124afc7af4188098704058bf067f0349b32c9a8c7f244499623d5d9f7b6e1fa986d'
);