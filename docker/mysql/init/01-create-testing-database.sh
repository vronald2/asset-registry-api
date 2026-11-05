#!/bin/sh
# Creates the database used by the automated test suite and grants the application user access to it.
# Runs once, when the MySQL data volume is initialised.

TESTING_DATABASE="${MYSQL_TESTING_DATABASE:-asset_registry_testing}"

mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" <<EOSQL
CREATE DATABASE IF NOT EXISTS \`${TESTING_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`${TESTING_DATABASE}\`.* TO '${MYSQL_USER}'@'%';
EOSQL
