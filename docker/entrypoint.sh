#!/bin/sh
set -e

cd /app

[ -f .env ] || cp .env.example .env

grep -q '^APP_KEY=base64' .env || php artisan key:generate --force

php artisan migrate --force

if [ "$(php artisan tinker --execute='echo App\Models\Sensor::count();')" = "0" ]; then
    (php artisan air:discover && php artisan air:import --days="${HISTORY_DAYS:-92}") >> storage/logs/first-import.log 2>&1 &
fi

php artisan schedule:work >> storage/logs/scheduler.log 2>&1 &

exec php artisan serve --host=0.0.0.0 --port=8000
