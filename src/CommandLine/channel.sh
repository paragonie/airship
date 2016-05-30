#!/usr/bin/env bash

basedir=$( dirname $( readlink -f ${BASH_SOURCE[0]} ) )

php ${basedir}/channel.php $*
