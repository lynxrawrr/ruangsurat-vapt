#!/bin/sh
set -eu
case "${APP_MODE:-}" in vulnerable|fixed) ;; *) echo 'APP_MODE harus vulnerable atau fixed' >&2; exit 1;; esac
php /var/www/html/console.php seed
if [ "$APP_MODE" = fixed ]; then
    exec apache2-foreground -D LAB_FIXED
fi
exec apache2-foreground
