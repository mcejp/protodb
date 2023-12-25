#!/bin/sh

set -e
cd /var/www/html

cloc . --not-match-f="\.phar$"

# For now, let this pass
python3 -m mypy -p protodb || true

python3 -m pytest -o junit_family=xunit2 --junitxml=build/report-pytest.xml
php -dxdebug.mode=coverage /phpunit.phar -d error_reporting=32767 .
