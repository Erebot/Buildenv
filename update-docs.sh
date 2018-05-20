#!/bin/bash

function die()
{
    echo "Error during this call:" >&2
    caller 0 >&2
    exit 1
}
trap die ERR

# We only trigger a build for the 1st job in a build,
# silently ignoring other jobs.
if [ "${TRAVIS_JOB_NUMBER##*.}" != "1" ]; then
    echo "Not triggering a build for job #$TRAVIS_JOB_NUMBER" >&2
    exit 0
fi

if [ -z "$TRAVIS_TOKEN" ]; then
    echo "Missing travis API authentication token (TRAVIS_TOKEN)" >&2
    exit 1
fi

if [ -n "$TRAVIS_PULL_REQUEST_SLUG" ]; then
    echo "Refusing to trigger a build for PR from '$TRAVIS_PULL_REQUEST_SLUG'" >&2
    exit 1
fi

prefix="TRAVIS_"

i=0
environment=""
while read -r -d "$(printf '\0')" var; do
    var=${var%%=*}
    if [ "${var:0:${#prefix}}" = "$prefix" ] && [ "$var" != "TRAVIS_TOKEN" ]; then
        if [ $i -gt 0 ]; then
            sep=","
        else
            sep=""
        fi

        escaped_value=`declare -p "$var" | cut -d= -f2-`
        read -r environment <<EOF
${environment}${sep} "ORIG_${var}": ${escaped_value}
EOF
        i=$((i+1))
    fi
done < <(env -0)

request=$(cat <<EOF
{
  "request": {
    "branch": "$TRAVIS_BRANCH",
    "message": "Triggered by ${TRAVIS_COMMIT:0:7} in $TRAVIS_REPO_SLUG",
    "config": {
      "merge_mode": "deep_merge",
      "env": {$environment}
    }
  }
}
EOF
)

curl -s -X POST -d "$request" \
    -H "Content-Type: application/json"         \
    -H "Accept: application/json"               \
    -H "Travis-API-Version: 3"                  \
    -H "Authorization: token ${TRAVIS_TOKEN}"   \
    https://api.travis-ci.org/repo/Erebot%2Ferebot.github.io/requests
