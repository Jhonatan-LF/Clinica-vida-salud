FROM php:8.2-cli-alpine

# Instalar librerías de PostgreSQL y extensiones PHP necesarias (pdo_pgsql)
RUN apk add --no-cache libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Directorio de trabajo
WORKDIR /var/www/html

# Copiar todo el código del proyecto
COPY . .

# Puerto por defecto (Render lo sobreescribe mediante la variable PORT)
ENV PORT=10000
EXPOSE 10000

# Comando para iniciar la aplicación usando router.php
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} backend/public/router.php"]
