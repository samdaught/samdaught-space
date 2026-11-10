#!/bin/sh
set -eu
umask 077

if [ "$#" -ne 0 ]; then
    echo "Usage: sudo sh scripts/backup-db.sh" >&2
    exit 2
fi

project_directory=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
if [ ! -f "$project_directory/.env.production" ]; then
    echo "Configure .env.production before backing up the production database." >&2
    exit 1
fi

compose() {
    docker compose --project-directory "$project_directory" \
        --project-name samdaught-space-prod \
        --env-file "$project_directory/.env.production" \
        --file "$project_directory/compose.production.yml" "$@"
}

backup_directory=/var/backups/samdaught-space
mkdir -p -- "$backup_directory"
chmod 700 -- "$backup_directory"
exec 9>"$backup_directory/.backup.lock"
if ! flock -n 9; then
    echo "Another database backup is already running." >&2
    exit 1
fi

partial=$(mktemp --suffix=.dump.partial "$backup_directory/samdaught-$(date -u +%Y%m%dT%H%M%SZ)-XXXXXX")
trap 'rm -f -- "$partial"' EXIT
trap 'exit 1' HUP INT TERM

compose exec -T postgres sh -ceu '
    export PGPASSWORD="$POSTGRES_PASSWORD"
    exec pg_dump --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" \
        --format=custom --no-owner --no-acl
' > "$partial"

test -s "$partial"
# Check the archive header/table of contents; a full restore still needs a rehearsal.
compose exec -T postgres pg_restore --list < "$partial" > /dev/null
dump_file=${partial%.partial}
mv -- "$partial" "$dump_file"
printf 'Database backup created: %s\n' "$dump_file"
