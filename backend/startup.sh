#!/bin/bash
set -e

echo " Starting Laravel Application..."

cd /home/site/wwwroot

# Verificar que estamos en el directorio correcto
if [ ! -f "artisan" ]; then
    echo " Error: artisan file not found in /home/site/wwwroot"
    exit 1
fi

echo " Found artisan file"

# Configurar nginx para apuntar al directorio public de Laravel
echo " Configuring nginx document root..."
cat > /etc/nginx/sites-available/default <<'NGINX_CONF'
server {
    listen 8080;
    listen [::]:8080;
    root /home/site/wwwroot/public;
    index index.php index.html;
    server_name _;

    client_max_body_size 100M;

    # Handle preflight OPTIONS requests first
    location / {
        # Preflight requests
        if ($request_method = 'OPTIONS') {
            add_header 'Access-Control-Allow-Origin' 'https://demo.example.org' always;
            add_header 'Access-Control-Allow-Methods' 'GET, POST, PUT, DELETE, OPTIONS, PATCH' always;
            add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-XSRF-TOKEN' always;
            add_header 'Access-Control-Allow-Credentials' 'true' always;
            add_header 'Access-Control-Max-Age' '86400' always;
            add_header 'Content-Length' '0';
            add_header 'Content-Type' 'text/plain';
            return 204;
        }

        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        # CORS headers for PHP responses
        add_header 'Access-Control-Allow-Origin' 'https://demo.example.org' always;
        add_header 'Access-Control-Allow-Methods' 'GET, POST, PUT, DELETE, OPTIONS, PATCH' always;
        add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-XSRF-TOKEN' always;
        add_header 'Access-Control-Allow-Credentials' 'true' always;

        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX_CONF

echo " Nginx configured"

# Reiniciar nginx para aplicar cambios
nginx -s reload 2>/dev/null || nginx

echo " Nginx reloaded"

# Crear directorios necesarios si no existen
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs
mkdir -p bootstrap/cache

echo " Directories created"

# Configurar permisos (775 en lugar de 777 por seguridad)
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

echo " Permissions set"

# =============================================================================
# GENERAR .env DESDE VARIABLES DE AZURE APP SERVICE
# =============================================================================
# En Azure, las variables de entorno se pasan al contenedor, NO como archivo .env
# Necesitamos crearlas dinámicamente

echo " Generating .env from Azure App Service environment variables..."


cat > .env <<'ENV_FILE'
# =============================================================================
# APLICACIÓN (Generado dinámicamente desde Azure App Service)
# =============================================================================
APP_NAME="${APP_NAME}"
APP_ENV="${APP_ENV}"
APP_DEBUG="${APP_DEBUG}"
APP_KEY="${APP_KEY}"
APP_URL="${APP_URL}"
FRONTEND_URL="${FRONTEND_URL}"

# =============================================================================
# BASE DE DATOS - PRINCIPAL
# =============================================================================
DB_CONNECTION="${DB_CONNECTION}"
DB_HOST="${DB_HOST}"
DB_PORT="${DB_PORT}"
DB_DATABASE="${DB_DATABASE}"
DB_USERNAME="${DB_USERNAME}"
DB_PASSWORD="${DB_PASSWORD}"

# =============================================================================
# BASE DE DATOS - SECUNDARIA (Sistema Externo de Alertas)
# =============================================================================
ALERTS_DB_HOST="${ALERTS_DB_HOST}"
ALERTS_DB_PORT="${ALERTS_DB_PORT}"
ALERTS_DB_DATABASE="${ALERTS_DB_DATABASE}"
ALERTS_DB_USERNAME="${ALERTS_DB_USERNAME}"
ALERTS_DB_PASSWORD="${ALERTS_DB_PASSWORD}"

# =============================================================================
# AUTENTICACIÓN - MICROSOFT AZURE AD
# =============================================================================
AZURE_CLIENT_ID="${AZURE_CLIENT_ID}"
AZURE_CLIENT_SECRET="${AZURE_CLIENT_SECRET}"
AZURE_TENANT_ID="${AZURE_TENANT_ID}"
AZURE_REDIRECT_URI="${AZURE_REDIRECT_URI}"

# =============================================================================
# SEGURIDAD - JWT
# =============================================================================
JWT_SECRET="${JWT_SECRET}"

# =============================================================================
# SERVICIOS - CACHE, QUEUE, LOGGING
# =============================================================================
CACHE_STORE="${CACHE_STORE}"
QUEUE_CONNECTION="${QUEUE_CONNECTION}"
LOG_LEVEL="${LOG_LEVEL}"

# =============================================================================
# SESIONES Y SEGURIDAD CORS
# =============================================================================
SESSION_DRIVER="${SESSION_DRIVER}"
SANCTUM_STATEFUL_DOMAINS="${SANCTUM_STATEFUL_DOMAINS}"
SESSION_DOMAIN="${SESSION_DOMAIN}"

# =============================================================================
# VALORES POR DEFECTO (Si no están definidos en Azure)
# =============================================================================
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
LOG_CHANNEL=stack
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
SESSION_LIFETIME=120
ENV_FILE

echo " .env generated from variables"

# Verificar que .env se creó correctamente
if [ -f ".env" ]; then
    echo " .env file created successfully"
    echo " .env variables set (sanitized - sensitive values masked):"
    grep -E "^(APP_|DB_|ALERTS_|JWT_|AZURE_|CACHE_|QUEUE_|LOG_|SESSION_|SANCTUM_)" .env | \
        sed 's/PASSWORD=.*/PASSWORD=***/' | \
        sed 's/SECRET=.*/SECRET=***/' | \
        sed 's/CLIENT_SECRET=.*/CLIENT_SECRET=***/' | \
        sort
    echo ""
    echo " Configuration validated - .env is ready"
else
    echo " Failed to create .env file"
    exit 1
fi

# Limpiar cachés anteriores
echo " Clearing caches..."
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true

echo " Caches cleared"

# NO cachear config - dejar que Laravel lea .env directamente
# Esto es más seguro en un contenedor efímero

# Solo cachear rutas y vistas (no dependen de .env dinámico)
echo " Caching routes and views..."
php artisan route:cache 2>/dev/null || echo "️  Route cache skipped"
php artisan view:cache 2>/dev/null || echo "️  View cache skipped"

echo " Caches optimized"

echo " Laravel startup completed successfully"
echo "Starting PHP-FPM..."

# Iniciar PHP-FPM en foreground
php-fpm

