#!/bin/sh

set -e
mkdir -p ~/.protodb-data-mariadb
env CURRENT_UID=$(id -u):$(id -g) docker-compose up --build --abort-on-container-exit
