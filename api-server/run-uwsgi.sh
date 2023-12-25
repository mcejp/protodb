#!/bin/sh

cd "$(dirname "$0")"

ARGS="--plugin /usr/lib/uwsgi/python_plugin.so \
      -s /tmp/api-server.sock \
      --chmod-socket=777 \
      --manage-script-name \
      --mount /api=main:app \
      --buffer-size=16384 \
      "

if [ "$PROTODB_DEVELOPMENT" = "1" ]
then
  env PYTHONPATH=.. uwsgi $ARGS --py-autoreload 3
else
  env PYTHONPATH=.. uwsgi $ARGS
fi
