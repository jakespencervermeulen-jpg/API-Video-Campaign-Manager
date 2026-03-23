#!/bin/bash
set -e

spin() {
    local msg="$1"
    shift
    local chars="⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏"
    local pid

    "$@" > /dev/null 2>&1 &
    pid=$!

    while kill -0 "$pid" 2>/dev/null; do
        for (( i=0; i<${#chars}; i++ )); do
            printf "\r  %s %s" "${chars:$i:1}" "$msg"
            sleep 0.1
        done
    done

    wait "$pid"
    printf "\r  ✓ %s\n" "$msg"
}

echo ""
echo "  ┌──────────────────────────────────────┐"
echo "  │     Video Campaign Manager - Setup   │"
echo "  └──────────────────────────────────────┘"
echo ""

# .env
if [ ! -f .env ]; then
    cp .env.example .env
    echo "  ✓ .env created"
else
    echo "  – .env already exists, skipping"
fi

# Dependencies
spin "Installing dependencies" composer install --quiet --no-interaction

# App key
spin "Generating application key" php artisan key:generate --quiet

# Wait for DB
printf "  ⠋ Waiting for database"
while ! php -r "new PDO('mysql:host=db;port=3306;dbname=video_campaign_manager','laravel','secret');" 2>/dev/null; do
    for c in ⠋ ⠙ ⠹ ⠸ ⠼ ⠴ ⠦ ⠧ ⠇ ⠏; do
        printf "\r  %s Waiting for database" "$c"
        sleep 0.1
    done
done
printf "\r  ✓ Database is ready     \n"

# Migrations
spin "Running migrations" php artisan migrate --force --quiet

# Permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
echo "  ✓ Permissions set"

# Swagger docs
spin "Generating API documentation" php artisan l5-swagger:generate

echo ""
echo "  ┌────────────────────────────────────────────────┐"
echo "  │  Setup complete!                               │"
echo "  │  API:  http://localhost:8088/api               │"
echo "  │  Docs: http://localhost:8088/api/documentation │"
echo "  └────────────────────────────────────────────────┘"
echo ""

exec php-fpm
