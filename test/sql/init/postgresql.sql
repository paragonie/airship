-- http://stackoverflow.com/a/18389184/2224584

DO
$do$
BEGIN

IF EXISTS (SELECT 1 FROM pg_database WHERE datname = 'phpunit') THEN
   RAISE NOTICE 'Database already exists';
ELSE
   PERFORM dblink_exec('dbname=' || current_database()  -- current db
                     , 'CREATE DATABASE phpunit');
END IF;

END
$do$;


-- http://stackoverflow.com/a/8099557/2224584
DO
$body$
BEGIN
  IF NOT EXISTS (
      SELECT *
      FROM   pg_catalog.pg_user
      WHERE  usename = 'phpunit') THEN

    CREATE ROLE phpunit LOGIN PASSWORD 'phpunit';
  END IF;
END
$body$;