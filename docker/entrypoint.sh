#!/usr/bin/env bash
set -euo pipefail

# Entrypoint for HTAShop API backend container
# - Waits for MariaDB to become available
# - Creates the database if it does not exist
# - Runs artisan migrations
# - Starts the original CMD (supervisord)

DB_HOST=${DB_HOST:-db}
DB_PORT=${DB_PORT:-3306}
DB_DATABASE=${DB_DATABASE:-htashop}
DB_USERNAME=${DB_USERNAME:-root}
DB_PASSWORD=${DB_PASSWORD:-}
# When using Docker secrets, uncomment the following to read the password:
# if [ -f /run/secrets/db_password ]; then
#   DB_PASSWORD=$(cat /run/secrets/db_password)
# fi
# How long (seconds) to wait for DB readiness before failing
DB_WAIT_TIMEOUT=${DB_WAIT_TIMEOUT:-120}
MIGRATE_LOCK_TIMEOUT=${MIGRATE_LOCK_TIMEOUT:-90}

echo "[entrypoint] waiting for database at ${DB_HOST}:${DB_PORT}..."

# Fast-path for one-off debug commands: if SKIP_STARTUP is set, exec the
# provided command immediately instead of performing DB waits/migrations.
# Useful for `docker run --rm -e SKIP_STARTUP=1 ... punchout-api sh -c 'cat /var/www/html/.env'`
if [ "${SKIP_STARTUP:-}" != "" ]; then
  echo "[entrypoint] SKIP_STARTUP set, running provided command immediately"
  if [ "$#" -gt 0 ]; then
    exec "$@"
  else
    # No args were provided; drop to an interactive shell for debugging
    exec /bin/sh
  fi
fi

# Ensure log and cache directories exist and are writable by the web user.
# We run these with sudo (passwordless sudo is configured in the image) and
# treat failures as non-fatal so container startup doesn't break on exotic
# filesystems or permission setups.
echo "[entrypoint] ensuring storage and cache directories exist and are writable..."
sudo mkdir -p /var/www/html/storage/logs /var/www/html/bootstrap/cache 2>/dev/null || true
sudo mkdir -p /var/www/html/storage/app/public/avatar /var/www/html/storage/app/public/images /var/www/html/storage/app/public/thumbnails /var/www/html/storage/app/public/changelog 2>/dev/null || true
sudo mkdir -p /var/www/html/storage/app/private/crash-reports /var/www/html/storage/app/private/files /var/www/html/storage/app/private/files/data /var/www/html/storage/app/private/files/softwares 2>/dev/null || true
sudo touch /var/www/html/storage/logs/laravel.log 2>/dev/null || true
sudo chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
sudo chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Ensure the storage symlink exists
echo "[entrypoint] ensuring storage symlink exists..."
if [ -L /var/www/html/public/storage ]; then
  echo "[entrypoint] storage symlink already exists"
else
  echo "[entrypoint] creating storage symlink..."
  sudo ln -svf /var/www/html/storage/app/public /var/www/html/public/storage 2>/dev/null || true
fi


# Helper to run mariadb/mysql client while handling empty DB_PASSWORD
# Usage: db_client_exec <client> [args...]
db_client_exec() {
  local client="$1"; shift
  if [ -z "${client}" ]; then
    return 1
  fi
  if [ -n "${DB_PASSWORD}" ]; then
    # pass -p with password when provided
    "$client" -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" "$@"
  else
    # omit -p when DB_PASSWORD is empty (some clients treat -p"" as interactive)
    "$client" -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USERNAME}" "$@"
  fi
}

wait_for_port() {
    local host="$1" port="$2" timeout=${3:-$DB_WAIT_TIMEOUT}
    local start now
    start=$(date +%s)
    echo "[entrypoint] waiting up to ${timeout}s for ${host}:${port} (tcp)..."
    while :; do
        # Prefer nc if available, otherwise try bash /dev/tcp
        if command -v nc >/dev/null 2>&1; then
            if nc -z "$host" "$port" 2>/dev/null; then
                echo "[entrypoint] TCP port ${host}:${port} is open (nc)"
                return 0
            fi
        else
            # fallback to /dev/tcp (works in bash/sh on most images)
            if (echo >/dev/tcp/"$host"/"$port") >/dev/null 2>&1; then
                echo "[entrypoint] TCP port ${host}:${port} is open (/dev/tcp)"
                return 0
            fi
        fi
    done
}

mariadb_connect_check() {
  # Support both 'mysql' and 'mariadb' client binaries
  local client=""
  if command -v mysql >/dev/null 2>&1; then
    client=mysql
  elif command -v mariadb >/dev/null 2>&1; then
    client=mariadb
  else
    return 2
  fi

  local start now timeout=${DB_WAIT_TIMEOUT}
  start=$(date +%s)
  echo "[entrypoint] attempting ${client} client connection checks for up to ${timeout}s..."
  while :; do
    # Use db_client_exec to avoid passing -p"" when DB_PASSWORD is empty and to capture errors
    if db_client_exec "${client}" -e "SELECT 1;" >/dev/null 2>&1; then
      echo "[entrypoint] ${client} client connection succeeded"
      return 0
    else
      # capture a short sample of the client error to aid debugging
      ERR=$(db_client_exec "${client}" -e "SELECT 1;" 2>&1 || true)
      echo "[entrypoint] ${client} client connection failed: $(echo "$ERR" | sed -n '1,3p' | tr "\n" ' ' )" >&2
    fi
    now=$(date +%s)
    if [ $((now - start)) -ge $timeout ]; then
      echo "[entrypoint] ${client} client connection timeout after ${timeout}s" >&2
      return 1
    fi
    sleep 2
  done
}


# First attempt a TCP port check, then (if available) a mariadb client check
if ! wait_for_port "$DB_HOST" "$DB_PORT" "$DB_WAIT_TIMEOUT"; then
  echo "[entrypoint] database did not become ready in time (tcp check)" >&2
  exit 1
fi

# Prefer a real mariadb handshake if client exists
if command -v mariadb >/dev/null 2>&1; then
  if ! mariadb_connect_check; then
    echo "[entrypoint] database did not accept mariadb connections in time" >&2
    exit 1
  fi
fi

echo "[entrypoint] database reachable, ensuring database '${DB_DATABASE}' exists..."

create_db() {
  # Use mariadb client if available, otherwise bail with helpful message
  if command -v mariadb >/dev/null 2>&1; then
    db_client_exec mariadb -e "CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  else
    echo "[entrypoint] mariadb client not found in image; skipping DB creation. Ensure database '${DB_DATABASE}' exists." >&2
    return 0
  fi
}

create_db || true

echo "[entrypoint] running migrations (artisan)..."

run_migrations_with_lock() {
  # Only run if artisan exists
  if [ ! -f artisan ]; then
    echo "[entrypoint] artisan not found in /var/www/html - skipping migrations" >&2
    return 0
  fi

  # If no DB client is available, fall back to running migrations directly
  local db_client=""
  if command -v mariadb >/dev/null 2>&1; then
    db_client=mariadb
  elif command -v mysql >/dev/null 2>&1; then
    db_client=mysql
  fi

  if [ -z "${db_client}" ]; then
    echo "[entrypoint] mariadb/mysql client not found - running migrations without distributed lock"
    php artisan migrate --force || {
      echo "[entrypoint] artisan migrate failed" >&2
      return 1
    }
    return 0
  fi

  LOCK_NAME="htashop_migrations_lock"
  local start now elapsed
  start=$(date +%s)
  echo "[entrypoint] attempting to acquire migration lock '${LOCK_NAME}' (timeout=${MIGRATE_LOCK_TIMEOUT}s) using ${db_client} client..."

  while :; do
    # request a short (1s) lock attempt so we can loop and timeout gracefully
  ACQUIRED=$(db_client_exec "${db_client}" -N -s -e "SELECT GET_LOCK('${LOCK_NAME}', 1);" 2>/dev/null || echo 0)
    if [ "${ACQUIRED}" = "1" ]; then
      echo "[entrypoint] migration lock acquired by this container"
      # Run migrations while holding the lock
      if php artisan migrate --force; then
        echo "[entrypoint] artisan migrate completed successfully"
      else
        echo "[entrypoint] artisan migrate failed" >&2
  # Attempt to release the lock before exiting
  db_client_exec "${db_client}" -N -s -e "SELECT RELEASE_LOCK('${LOCK_NAME}');" >/dev/null 2>&1 || true
        return 1
      fi
      # Release lock explicitly
  db_client_exec "${db_client}" -N -s -e "SELECT RELEASE_LOCK('${LOCK_NAME}');" >/dev/null 2>&1 || true
      echo "[entrypoint] migration lock released"
      return 0
    fi

    now=$(date +%s)
    elapsed=$((now - start))
    if [ ${elapsed} -ge ${MIGRATE_LOCK_TIMEOUT} ]; then
      echo "[entrypoint] timeout (${MIGRATE_LOCK_TIMEOUT}s) waiting for migration lock - giving up" >&2
      return 2
    fi
    sleep 2
  done
}

if [ "${SKIP_MIGRATIONS:-}" != "" ]; then
  echo "[entrypoint] SKIP_MIGRATIONS is set, skipping migration execution"
elif [ -x /usr/bin/php ] || [ -x /usr/local/bin/php ]; then
  if ! run_migrations_with_lock; then
    # If the lock timeout occurred (return code 2), assume another node completed migrations.
    # For other failures, fail the container start so the operator can inspect logs.
    rc=$?
    if [ "$rc" -eq 2 ]; then
      echo "[entrypoint] continuing without running migrations (another node likely ran them)"
    else
      echo "[entrypoint] migrations failed (rc=${rc})" >&2
      exit 1
    fi
  fi
else
  echo "[entrypoint] PHP CLI not found; cannot run migrations" >&2
fi

echo "[entrypoint] migrations completed, execing CMD..."


# If CMD is provided as arguments, exec them; otherwise start supervisord
if [ "$#" -gt 0 ]; then
  exec "$@"
else
  exec /usr/bin/supervisord -n
fi
