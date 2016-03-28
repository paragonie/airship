ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO phpunit;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, USAGE ON sequences TO phpunit;

CREATE TABLE test_values (
  rowid BIGSERIAL PRIMARY KEY,
  name TEXT,
  foo BOOLEAN,
  created TIMESTAMP,
  modified TIMESTAMP
);

CREATE TABLE test_secondary (
  secondaryid BIGSERIAL PRIMARY KEY,
  test BIGINT REFERENCES test_values (rowid),
  numerical INTEGER,
  birthdate DATE NULL,
  extra_data JSONB
);