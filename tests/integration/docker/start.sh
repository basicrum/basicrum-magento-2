#!/usr/bin/env sh
set -eu
module_root=$(CDPATH= cd -- "$(dirname -- "$0")/../../.." && pwd)
cd "$module_root"
mkdir -p .test-results/baseline-certs
# Only ignored output needs to be writable by the unprivileged container user.
chmod a+rwx .test-results
openssl req -x509 -nodes -newkey rsa:2048 \
    -keyout .test-results/baseline-certs/key.pem -out .test-results/baseline-certs/cert.pem \
    -days 7 -subj '/CN=web' -addext 'subjectAltName=DNS:web,IP:127.0.0.1'
# The key is disposable and serves only this localhost-bound test stack.
chmod 644 .test-results/baseline-certs/key.pem
docker compose -f tests/integration/docker/compose.yaml build php
docker compose -f tests/integration/docker/compose.yaml up -d db search php
docker compose -f tests/integration/docker/compose.yaml exec -T --user root php chown app:app /var/www/html
# Use the caller's host identity for the bind-mounted checkout, not container root.
docker compose -f tests/integration/docker/compose.yaml exec -T \
    --user "$(id -u):$(id -g)" -e npm_config_cache="/tmp/basicrum-npm-$(id -u)" -w /module php npm ci
docker compose -f tests/integration/docker/compose.yaml exec -T php sh /module/tests/integration/docker/provision.sh
docker compose -f tests/integration/docker/compose.yaml up -d web
