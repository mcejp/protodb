#!/bin/sh

set -e
mkdir -p build /tmp/protodb_test_data
docker build -t protodb:latest -f container/Dockerfile .
env CURRENT_UID=$(id -u):$(id -g) docker-compose -p candbtests -f docker-tests.yml up --build --abort-on-container-exit
