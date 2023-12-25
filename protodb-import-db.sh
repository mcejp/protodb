#!/bin/bash

set -e

docker exec -i protodb_mariadb_1 mysql --init-command="SET SESSION FOREIGN_KEY_CHECKS=0;" candb -u candb --password=password
