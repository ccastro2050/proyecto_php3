# ==============================================================
# API Facturas v3 — PHP puro (puerto 8086)
# El servidor embebido de PHP con index.php como router: todas
# las peticiones pasan por el front controller sin configurar
# Apache/nginx. En desarrollo, el compose monta el código como
# volumen: guardar un .php = refrescar (PHP reinterpreta).
#
# LO QUE CAMBIA EN LA v3: la imagen trae los controladores de LOS
# DOS motores. Es el mismo programa, y por eso es UNA sola imagen
# — cuál de los dos usa se decide al arrancar, con la variable
# MOTOR, no aquí.
# ==============================================================
FROM php:8.3-cli

# El driver de PostgreSQL necesita sus cabeceras para compilarse
# (libpq-dev). El de MySQL/MariaDB ya viene con lo necesario.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_mysql pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# El código (en desarrollo queda tapado por el volumen del compose)
COPY . .

EXPOSE 8086

CMD ["php", "-S", "0.0.0.0:8086", "index.php"]
