#!/usr/bin/env bash

./nuke-backups.sh
./nuke-cache.sh
rm -rf ../src/tmp/cache/csp_hash/*/*
rm -rf ../src/tmp/cache/markdown/*/*
rm -rf ../src/tmp/cache/static/*/*
rm -rf ../src/tmp/cache/twig/*/*
rm -rf ../src/tmp/cache/*.json