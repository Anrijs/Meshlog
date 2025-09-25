#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="/var/www/html"

echo "$TIMEZONE" > /etc/timezone || true
cp "/usr/share/zoneinfo/$TIMEZONE" /etc/localtime || true

if [[ ! -f "$APP_ROOT/config.php" ]]; then
	cp "$APP_ROOT/config.example.php" "$APP_ROOT/config.php"
fi

sed -i \
	-e "s/^\(\s*\$servername\s*=\s*\).*/\1\"${DB_HOST}\";/" \
	-e "s/^\(\s*\$dbname\s*=\s*\).*/\1\"${DB_NAME}\";/" \
	-e "s/^\(\s*\$username\s*=\s*\).*/\1\"${DB_USER}\";/" \
	-e "s/^\(\s*\$password\s*=\s*\).*/\1\"${DB_PASS}\";/" \
	"$APP_ROOT/config.php"

exec "$@"