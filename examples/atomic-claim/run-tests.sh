#!/usr/bin/env sh
# Corre los dos tests de concurrencia contra la base levantada con docker compose.
# Requisitos: PHP 8.1+ con pdo_pgsql, y `docker compose up -d` ya ejecutado en la raíz del repo.
set -e
cd "$(dirname "$0")"

echo "== 1) Implementación atómica (debe pasar) =="
php tests/concurrency_test.php

echo
echo "== 2) Implementación ingenua (el test debe detectar la carrera) =="
php tests/concurrency_test.php --naive
