#!/bin/sh

# The official image sources non-executable init scripts; keep shell settings local.
(
    set -eu
    : "${APP_DB_USER:?APP_DB_USER is required}"
    : "${APP_DB_PASSWORD:?APP_DB_PASSWORD is required}"
    if [ "$APP_DB_USER" = "$POSTGRES_USER" ]; then
        echo "Application and database administrator roles must differ." >&2
        exit 1
    fi
    export PGPASSWORD="$POSTGRES_PASSWORD"
    psql --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" \
        --set=ON_ERROR_STOP=1 \
        --set=app_user="$APP_DB_USER" \
        --set=app_password="$APP_DB_PASSWORD" \
        --set=app_database="$POSTGRES_DB" <<'SQL'
SET log_statement = 'none';
SET log_min_error_statement = 'panic';
CREATE ROLE :"app_user" LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION PASSWORD :'app_password';
ALTER DATABASE :"app_database" OWNER TO :"app_user";
GRANT USAGE, CREATE ON SCHEMA public TO :"app_user";
SQL
)
