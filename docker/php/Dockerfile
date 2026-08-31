FROM php:8.3-fpm-alpine

# Outils système et dépendances nécessaires
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    icu-dev \
    linux-headers \
    $PHPIZE_DEPS

# Extensions PHP indispensables pour Symfony et MySQL
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    intl \
    opcache \
    zip

# Installer Composer directement dans l'image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Définir le dossier de travail dans le conteneur
WORKDIR /var/www/backend

EXPOSE 9000
CMD ["php-fpm"]