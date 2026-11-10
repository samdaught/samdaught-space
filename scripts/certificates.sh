#!/bin/sh
set -eu

action=${1:-}
case "$action" in
    issue|renew|check) ;;
    *) echo "Usage: sh scripts/certificates.sh issue|renew|check" >&2; exit 2 ;;
esac

project_directory=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
if [ ! -f "$project_directory/.env.production" ]; then
    echo "Configure .env.production before certificate operations." >&2
    exit 1
fi

compose() {
    docker compose --project-directory "$project_directory" \
        --project-name samdaught-space-prod \
        --env-file "$project_directory/.env.production" \
        --file "$project_directory/compose.production.yml" "$@"
}

exec 9>"$project_directory/.certbot.lock"
if ! flock -n 9; then
    echo "Another certificate operation is already running." >&2
    exit 1
fi

case "$action" in
    issue)
        compose run --rm --no-deps --entrypoint /bin/sh certbot -ceu '
            : "${CERTBOT_EMAIL:?Set CERTBOT_EMAIL in .env.production}"
            exec certbot certonly --non-interactive --agree-tos --keep-until-expiring \
                --webroot --webroot-path /var/www/certbot \
                --cert-name samdaught.space --domain samdaught.space --email "$CERTBOT_EMAIL"
        '
        ;;
    renew)
        compose run --rm --no-deps certbot renew --non-interactive --cert-name samdaught.space
        ;;
    check)
        compose run --rm --no-deps certbot renew --non-interactive --cert-name samdaught.space --dry-run
        exit 0
        ;;
esac

# Successful renew also means "not due yet"; a graceful reload is safe in either case.
compose exec -T php /usr/local/bin/samdaught-entrypoint reload-https
