#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
profile="${1:-vulnerable}"
case "$profile" in vulnerable) port=8080;; fixed) port=8081;; *) echo 'Gunakan vulnerable atau fixed' >&2; exit 1;; esac
if ! php -r 'exit(in_array("sqlite", PDO::getAvailableDrivers()) ? 0 : 1);'; then
  echo 'PHP perlu ekstensi PDO SQLite. Di Debian/Kali: sudo apt install php-sqlite3' >&2
  exit 1
fi
export APP_MODE="$profile" APP_DATA="$PWD/.runtime/$profile" APP_BUILD=local
php app/console.php seed
echo "Local: http://127.0.0.1:$port"
exec php -S "127.0.0.1:$port" -t app/public app/public/router.php
