#!/bin/sh

# Function to handle termination signals
terminate() {
    echo "Terminating processes..."
    kill -TERM "$php_fpm_pid" 2>/dev/null
    wait "$php_fpm_pid"
    exit 0
}

# Trap SIGTERM signal
trap terminate TERM

if [ "$#" -gt 0 ]; then
    case "$1" in
        storage:link)
            # Check for symlink at public/storage
            if [ ! -L "public/storage" ]; then
                echo "Creating storage symlink..."
                output=$(php artisan storage:link 2>&1)
                if echo "$output" | grep -q "The \[public/storage\] link already exists."; then
                    echo "Storage folder [public/storage] already exists hence it's not a symlink. Please delete it first before continuing"
                    exit 0
                elif [ $? -ne 0 ]; then
                    echo "Failed to create storage symlink. Exiting..."
                    echo "$output"
                    exit 1
                fi
            fi
            ;;
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
    php-fpm &
    php_fpm_pid=$!
    wait "$php_fpm_pid"
fi
