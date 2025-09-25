#!/usr/bin/env bash
set -euo pipefail

mysql -u root -p"${MYSQL_ROOT_PASSWORD}" "${MYSQL_DATABASE}" < /docker-entrypoint-initdb.d/10-setup.sql