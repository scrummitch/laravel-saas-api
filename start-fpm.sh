#!/bin/sh
set -ex

/usr/bin/php /srv/app/artisan optimize

/usr/sbin/php-fpm --nodaemonize --allow-to-run-as-root --force-stderr --fpm-config /etc/php83/php-fpm.conf
