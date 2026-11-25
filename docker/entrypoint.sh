#!/bin/sh
set -eu

cd /var/www/html

export PORT="${PORT:-10000}"
export APP_ENV="${APP_ENV:-production}"
export DB_CONNECTION="${DB_CONNECTION:-pgsql}"
export DB_SSLMODE="${DB_SSLMODE:-require}"

# Neon calls the full connection string DATABASE_URL; Laravel reads DB_URL.
# Keep an explicitly supplied DB_URL if both are present.
if [ -z "${DB_URL:-}" ] && [ -n "${DATABASE_URL:-}" ]; then
    export DB_URL="$DATABASE_URL"
fi

: "${APP_KEY:?Set APP_KEY in the Render dashboard.}"

APP_DB_URL="${DB_URL:-}"

if [ -z "$APP_DB_URL" ]; then
    : "${DB_HOST:?Set DB_URL, DATABASE_URL, or DB_HOST with DB_DATABASE/DB_USERNAME/DB_PASSWORD.}"
    : "${DB_DATABASE:?Set DB_DATABASE when using individual DB_* variables.}"
    : "${DB_USERNAME:?Set DB_USERNAME when using individual DB_* variables.}"
    : "${DB_PASSWORD:?Set DB_PASSWORD when using individual DB_* variables.}"
fi

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

# nginx cannot expand environment variables by itself; substitute only PORT so
# PHP variables in the template are left untouched.
envsubst '${PORT}' \
    < /etc/nginx/http.d/default.conf.template \
    > /etc/nginx/http.d/default.conf

# Retry only transient database connection/startup failures (for example a Neon
# compute waking from idle). Bad credentials, invalid SQL, or missing drivers
# fail immediately so they are not silently retried.
is_retryable_db_error() {
    grep -Eqi \
        'SQLSTATE\[08006\]|could not translate host name|connection refused|could not connect|connection timed out|server closed the connection unexpectedly|no route to host|network is unreachable|connection reset by peer|database system is starting up|deadlock detected|too many connections|timeout expired' \
        "$1"
}

retry_database_command() {
    description="$1"
    shift

    attempt=1
    max_attempts=5
    delay_seconds=5

    while [ "$attempt" -le "$max_attempts" ]; do
        log_file=$(mktemp)

        # Keep the original output visible; capture a copy only to decide
        # whether this failure is worth retrying.
        if "$@" > "$log_file" 2>&1; then
            cat "$log_file"
            rm -f "$log_file"
            return 0
        fi

        cat "$log_file" >&2
        if ! is_retryable_db_error "$log_file"; then
            rm -f "$log_file"
            echo "ERROR: $description failed with a non-retryable error." >&2
            return 1
        fi
        rm -f "$log_file"

        if [ "$attempt" -eq "$max_attempts" ]; then
            echo "ERROR: $description failed after $max_attempts attempts; database connection is still unavailable." >&2
            return 1
        fi

        echo "$description failed (attempt $attempt/$max_attempts); retrying in ${delay_seconds}s..." >&2
        sleep "$delay_seconds"
        attempt=$((attempt + 1))
    done

    return 1
}

run_database_setup() {
    # Additive and idempotent. Never use migrate:fresh or reset deployed data.
    retry_database_command 'Migration' php artisan migrate --force --no-interaction

    # Optional for the demo dataset. Seeders are idempotent by natural key.
    case "${SEED_DEMO_DATA:-false}" in
        true|TRUE|True|1|yes|YES|Yes)
            retry_database_command 'Seeding' php artisan db:seed --force --no-interaction
            ;;
    esac
}

# Neon recommends the pooled endpoint for the web app and the direct endpoint
# for schema migrations. MIGRATION_DB_URL is optional; without it, DB_URL is
# used for both. Migration runs before config:cache so the temporary URL is not
# baked into the cached application config.
if [ -n "${MIGRATION_DB_URL:-}" ]; then
    export DB_URL="$MIGRATION_DB_URL"
    run_database_setup

    if [ -n "$APP_DB_URL" ]; then
        export DB_URL="$APP_DB_URL"
    else
        unset DB_URL
    fi
else
    run_database_setup
fi

# Cache config at container start, after dashboard environment variables exist.
# Route caching is intentionally skipped because routes/web.php uses a closure.
php artisan config:cache --no-interaction

exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
