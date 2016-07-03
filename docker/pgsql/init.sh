su postgres -c "createuser airship"
su postgres -c "createdb -O airship airship"
su postgres -c "psql -c \"ALTER USER airship PASSWORD 'secret'\""
