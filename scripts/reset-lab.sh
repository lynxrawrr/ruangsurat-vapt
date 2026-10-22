#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
profile="${1:-}"
case "$profile" in vulnerable|fixed) ;; *) echo 'Gunakan: ./scripts/reset-lab.sh vulnerable|fixed' >&2; exit 1;; esac
docker compose --profile "$profile" exec -T "$profile" php /var/www/html/console.php reset
echo 'Data dan sesi profil direset. Evidence tidak disentuh. Login ulang di browser.'
