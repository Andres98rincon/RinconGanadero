#!/usr/bin/env bash
#
# Despliegue de Rincón Ganadero en el servidor (Hostinger).
# Uso, dentro de la carpeta laravel del servidor:   bash deploy.sh
#
# Hace en orden: modo mantenimiento → trae el código → dependencias → migraciones →
# datos base → limpia y recrea cachés → vuelve a abrir la app. Si algo falla, la app
# se vuelve a abrir igual y el error queda en pantalla.
set -euo pipefail

cd "$(dirname "$0")"

echo "▶ Activando modo mantenimiento"
php artisan down --retry=15 --refresh=15 || true
trap 'echo "✖ El despliegue falló. Reabriendo la app."; php artisan up' ERR

echo "▶ Descargando la última versión de main"
git pull --ff-only origin main

echo "▶ Instalando dependencias de PHP"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

echo "▶ Ejecutando migraciones"
php artisan migrate --force

echo "▶ Cargando datos base (roles, especies, razas, departamentos)"
php artisan db:seed --class=SystemSeeder --force

# En Hostinger el dominio sirve ../public_html, una copia de laravel/public.
# Se sincronizan los archivos públicos sin tocar el index.php adaptado ni el build (lo sube GitHub Actions).
PUBLIC_HTML="${PUBLIC_HTML:-../public_html}"
if [ -d "$PUBLIC_HTML" ] && [ ! -L "$PUBLIC_HTML" ]; then
    echo "▶ Sincronizando archivos públicos con $PUBLIC_HTML"
    if command -v rsync >/dev/null 2>&1; then
        rsync -a --exclude 'index.php' --exclude '.htaccess' --exclude 'build/' --exclude 'storage' --exclude 'hot' public/ "$PUBLIC_HTML/"
    else
        (cd public && find . -type f ! -name 'index.php' ! -name '.htaccess' ! -name 'hot' ! -path './build/*' ! -path './storage/*' -exec cp --parents {} "../$PUBLIC_HTML/" \;)
    fi
    if [ ! -e "$PUBLIC_HTML/storage" ]; then
        ln -s "$(pwd)/storage/app/public" "$PUBLIC_HTML/storage" || true
    fi
fi

echo "▶ Limpiando y recreando cachés"
php artisan optimize:clear
php artisan optimize

php artisan up
trap - ERR
echo "✔ Despliegue terminado"
