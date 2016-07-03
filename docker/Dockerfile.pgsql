FROM postgres:9.5

COPY docker/pgsql/init.sh /docker-entrypoint-initdb.d/init.sh
