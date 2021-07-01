#!/usr/bin/env bash

set -e

git pull --rebase --autostash
docker-compose down
docker-compose up --build --detach
docker exec mj-bot-discord vendor/bin/doctrine orm:schema-tool:update --force --dump-sql
