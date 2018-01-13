#!/usr/bin/env bash

echo "Checking the engine..."
echo -e "Using \e[32mpsalm.xml\e[39m:"
status=0

vendor/bin/psalm
status=$(($status + $?))

echo "Checking each Cabin:"
for dir in src/Cabin/*
do
    if [[ -d "${dir}" ]]; then
        if [[ -f "${dir}/psalm.xml" ]]; then
            echo -e "Using \e[32m${dir}/psalm.xml\e[39m:"
            vendor/bin/psalm --config="${dir}/psalm.xml"
            status=$(($status + $?))
        else
            echo -e "Cannot test Cabin; \e[31m${dir}/psalm.xml\e[39m not found."
        fi
    fi
done

exit $status
