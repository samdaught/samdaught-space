#!/bin/sh
set -eu

configure_apache() {
    certificate=/etc/letsencrypt/live/samdaught.space/fullchain.pem
    private_key=/etc/letsencrypt/live/samdaught.space/privkey.pem
    if [ -s "$certificate" ] && [ -s "$private_key" ]; then
        cp /opt/samdaught/apache-https.conf /etc/apache2/sites-available/000-default.conf
        printf 'Listen 80\nListen 443\n' > /etc/apache2/ports.conf
    elif [ -e "$certificate" ] || [ -e "$private_key" ]; then
        echo "TLS certificate or private key is incomplete; Apache configuration was not changed." >&2
        return 1
    else
        cp /opt/samdaught/apache-bootstrap.conf /etc/apache2/sites-available/000-default.conf
        printf 'Listen 80\n' > /etc/apache2/ports.conf
    fi
}

case "${1:-}" in
    apache2-foreground)
        configure_apache
        apache2ctl -t
        ;;
    reload-https)
        if [ ! -s /etc/letsencrypt/live/samdaught.space/fullchain.pem ] || [ ! -s /etc/letsencrypt/live/samdaught.space/privkey.pem ]; then
            echo "TLS certificate is missing; HTTPS reload was not performed." >&2
            exit 1
        fi
        configure_apache
        apache2ctl -t
        exec apache2ctl -k graceful
        ;;
esac

exec docker-php-entrypoint "$@"
