#!/bin/bash
set -e

cd /var/www/html

# Si no existe .env, lo genera a partir de las variables de entorno de EasyPanel
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Genera APP_KEY solo si no está seteada
if ! grep -q "^APP_KEY=base64" .env 2>/dev/null && [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Cachear configuración, rutas y vistas para producción
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Crear symlink de storage si no existe
if [ ! -L public/storage ]; then
    php artisan storage:link
fi

# Ejecutar migraciones automáticamente (opcional, ver nota abajo)
php artisan migrate --force

exec "$@"
