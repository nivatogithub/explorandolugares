#!/bin/sh
set -eu
mkdir -p /run/mysqld /var/lib/mysql /srv/state/private /srv/state/media
chown mysql:mysql /run/mysqld /var/lib/mysql
if [ ! -d /var/lib/mysql/mysql ]; then mysqld --initialize-insecure --user=mysql --datadir=/var/lib/mysql; fi
mysqld --user=mysql --bind-address=127.0.0.1 &
MYSQL_BOOT_PID=$!
trap 'kill "$MYSQL_BOOT_PID" 2>/dev/null || true' EXIT
for attempt in $(seq 1 90); do
 if mysqladmin ping --silent >/dev/null 2>&1; then break; fi
 sleep 1
done
mysqladmin ping --silent >/dev/null
mysql -e "CREATE DATABASE IF NOT EXISTS explorando_preprod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php /usr/local/lib/preprod-init.php
chown -R www-data:www-data /srv/state/private /srv/state/media
mysqladmin shutdown
wait "$MYSQL_BOOT_PID"
trap - EXIT
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
