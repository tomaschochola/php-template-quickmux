#!/bin/sh

set -e

php ./bin/migrate.php

exec "$@"
