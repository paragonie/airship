#!/usr/bin/env bash

# FILES=../../src/Installer/sql/pgsql/*
# for f in $FILES
# do
#     psql -d airship_test -U postgres < $f
# done

basedir=$( dirname $( dirname $( readlink -f ${BASH_SOURCE[0]} ) ) )

psql -d airship_test -U postgres < $basedir/Installer/sql/pgsql/00-procedures.sql
psql -d airship_test -U postgres < $basedir/Installer/sql/pgsql/10-users.sql
psql -d airship_test -U postgres < $basedir/Installer/sql/pgsql/11-groups.sql
psql -d airship_test -U postgres < $basedir/Installer/sql/pgsql/12-permissions.sql
psql -d airship_test -U postgres < $basedir/Installer/sql/pgsql/13-long-term-authentication.sql
psql -d airship_test -U postgres < $basedir/Installer/sql/pgsql/14-files.sql
psql -d airship_test -U postgres < $basedir/Installer/sql/pgsql/20-blog.sql
psql -d airship_test -U postgres < $basedir/Installer/sql/pgsql/21-blog-series.sql
psql -d airship_test -U postgres < $basedir/Installer/sql/pgsql/22-blog-authors.sql
psql -d airship_test -U postgres < $basedir/Installer/sql/pgsql/23-blog-comments.sql
psql -d airship_test -U postgres < $basedir/Installer/sql/pgsql/30-custom-pages.sql
psql -d airship_test -U postgres < $basedir/Installer/sql/pgsql/40-user-extra.sql
psql -d airship_test -U postgres < $basedir/Installer/sql/pgsql/41-account-recovery.sql
psql -d airship_test -U postgres < $basedir/Installer/sql/pgsql/42-rate-limiting.sql
psql -d airship_test -U postgres < $basedir/Installer/sql/pgsql/50-bridge-announcements.sql
psql -d airship_test -U postgres < $basedir/Installer/sql/pgsql/60-peer-verification.sql
psql -d airship_test -U postgres < $basedir/Installer/sql/pgsql/61-continuum.sql
psql -d airship_test -U postgres < $basedir/Installer/sql/pgsql/70-logging.sql
psql -d airship_test -U postgres < $basedir/Installer/sql/pgsql/90-view-blog.sql
psql -d airship_test -U postgres < $basedir/Installer/sql/pgsql/91-view-perms.sql
psql -d airship_test -U postgres < $basedir/Installer/sql/pgsql/99-inserts.sql