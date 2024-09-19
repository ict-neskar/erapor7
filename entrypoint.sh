#!/bin/sh

if [ "$#" -gt 0 ]; then
    case "$1" in
        migrate)
            php artisan migrate
            ;;
        seed)
            php artisan db:seed
            ;;
        migrate:seed)
            php artisan migrate
            php artisan db:seed
            ;;
        *)
            echo "Unknown argument: $1"
            exit 1
            ;;
    esac
else
    caddy run -c /app/Caddyfile
fi