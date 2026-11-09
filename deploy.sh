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

# Hostinger usa PHP 8.2 por defecto en la terminal aunque la web use 8.3.
# Se busca un PHP >= 8.3 y se pone primero en el PATH para php y composer.
PHP_BIN="${PHP_BIN:-}"
if [ -z "$PHP_BIN" ]; then
    for candidate in /opt/alt/php84/usr/bin/php /opt/alt/php83/usr/bin/php /usr/bin/php8.4 /usr/bin/php8.3 "$(command -v php || true)"; do
        if [ -n "$candidate" ] && [ -x "$candidate" ] && "$candidate" -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);'; then
            PHP_BIN="$candidate"
            break
        fi
    done
fi
if [ -z "$PHP_BIN" ]; then
    echo "✖ No se encontró PHP 8.3 o superior en el servidor." >&2
    exit 1
fi
mkdir -p "$HOME/.local/deploy-bin"
ln -sf "$PHP_BIN" "$HOME/.local/deploy-bin/php"
export PATH="$HOME/.local/deploy-bin:$PATH"
echo "▶ Usando $(php -r 'echo PHP_VERSION;') ($PHP_BIN)"

COMPOSER_BIN="$(command -v composer || true)"
run_composer() {
    if [ -n "$COMPOSER_BIN" ] && head -c 300 "$COMPOSER_BIN" | grep -q 'php'; then
        php "$COMPOSER_BIN" "$@"
    else
        composer "$@"
    fi
}

echo "▶ Activando modo mantenimiento"
php artisan down --retry=15 --refresh=15 || true
trap 'echo "✖ El despliegue falló. Reabriendo la app."; php artisan up' ERR

echo "▶ Descargando la última versión de main"
git pull --ff-only origin main

echo "▶ Instalando dependencias de PHP"
run_composer install --no-dev --optimize-autoloader --no-interaction --no-progress

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
