#!/bin/sh
set -ex

/usr/bin/php /srv/app/artisan optimize

/usr/bin/php /srv/app/artisan migrate --force --verbose

/usr/bin/php /srv/app/artisan queue:work --verbose
