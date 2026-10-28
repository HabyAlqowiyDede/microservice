#!/bin/sh
set -e
 
cd /var/www/html
 
# Bangkitkan APP_KEY sementara jika belum diberikan lewat environment.
if [ -z "$APP_KEY" ]; then
    export APP_KEY="$(php artisan key:generate --show)"
fi
 
# Buat tabel (idempoten: aman dijalankan berulang).
php artisan migrate --force
 
# --no-reload agar seluruh environment variable diteruskan ke server.
exec php artisan serve --host=0.0.0.0 --port=3003 --no-reload
