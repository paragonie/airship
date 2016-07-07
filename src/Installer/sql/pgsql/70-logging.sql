CREATE TABLE IF NOT EXISTS airship_continuum_log (
    logid BIGSERIAL PRIMARY KEY,
    loglevel TEXT,
    component TEXT,
    message TEXT,
    context JSONB,
    created TIMESTAMP DEFAULT NOW(),
    modified TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS ON airship_continuum_log (component);
CREATE INDEX IF NOT EXISTS ON airship_continuum_log (created);
CREATE INDEX IF NOT EXISTS ON airship_continuum_log (loglevel);

DROP TRIGGER IF EXISTS update_airship_continuum_log_modtime ON airship_continuum_log;
CREATE TRIGGER update_airship_continuum_log_modtime
  BEFORE UPDATE ON airship_continuum_log
  FOR EACH ROW EXECUTE PROCEDURE update_modtime();
