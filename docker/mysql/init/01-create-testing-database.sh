#!/bin/sh
# Executed by the MySQL image once, on the first start with an empty data volume: creates the database of the
# test suite (see phpunit.xml) and grants the application user access to it.
set -eu

mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" <<SQL
CREATE DATABASE IF NOT EXISTS \`asset_registry_testing\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`asset_registry_testing\`.* TO '${MYSQL_USER}'@'%';
SQL
