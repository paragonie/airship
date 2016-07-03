DROP TRIGGER IF EXISTS update_airship_package_versions_modtime ON airship_package_versions;
CREATE TRIGGER update_airship_package_versions_modtime
  BEFORE UPDATE ON airship_package_versions
  FOR EACH ROW EXECUTE PROCEDURE update_modtime();

DROP TRIGGER IF EXISTS update_airship_package_cache_modtime ON airship_package_cache;
CREATE TRIGGER update_airship_package_cache_modtime
  BEFORE UPDATE ON airship_package_cache
  FOR EACH ROW EXECUTE PROCEDURE update_modtime();