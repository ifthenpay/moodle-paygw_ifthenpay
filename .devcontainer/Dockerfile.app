# ─────────────────────────────────────────────────────────────────────────────
# Moodle runtime: Apache + PHP 8.3 + Xdebug, with a pinned Moodle release.
#
# The Moodle release is unpacked into /opt/moodle-src (immutable, part of the
# image). At container start the entrypoint syncs it into /var/www/html, which
# is a shared volume, and upgrades the database. Bumping MOODLE_VERSION and
# rebuilding is therefore all it takes to move to another Moodle release.
# ─────────────────────────────────────────────────────────────────────────────
FROM php:8.3-apache

# Host user to run as, so bind-mounted plugin files stay editable by everyone.
ARG APP_UID=1000
ARG APP_GID=1000
# Pinned Moodle release, verified against the published SHA-256 checksum.
ARG MOODLE_BRANCH=stable501
ARG MOODLE_VERSION=5.1.5
ARG XDEBUG_VERSION=3.4.0

# 1) System dependencies.
RUN apt-get update && apt-get install -y --no-install-recommends \
      ca-certificates \
      curl \
      default-mysql-client \
      git \
      libfreetype6-dev \
      libicu-dev \
      libjpeg-dev \
      libonig-dev \
      libpng-dev \
      libpq-dev \
      libxml2-dev \
      libzip-dev \
      locales \
      rsync \
      unzip \
      zip \
    && rm -rf /var/lib/apt/lists/*

# 2) PHP extensions required by Moodle.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        exif \
        gd \
        intl \
        mbstring \
        mysqli \
        opcache \
        pdo \
        pdo_mysql \
        soap \
        xml \
        zip

# 3) Xdebug. Settings are NOT left to the XDEBUG_MODE / XDEBUG_CONFIG
#    environment variables at runtime: this PHP/Xdebug build ignores those
#    once xdebug.mode is set in php.ini, so the entrypoint rewrites
#    zz-xdebug.ini from the environment on every start instead (see
#    app-entrypoint.sh, configure_xdebug). This baked-in file is only the
#    fallback used if the entrypoint is ever bypassed.
RUN pecl install "xdebug-${XDEBUG_VERSION}" \
    && docker-php-ext-enable xdebug \
    && { \
        echo 'xdebug.mode=off'; \
        echo 'xdebug.start_with_request=trigger'; \
        echo 'xdebug.client_host=ifthenpay-dev'; \
        echo 'xdebug.client_port=9003'; \
        echo 'xdebug.discover_client_host=false'; \
        echo 'xdebug.max_nesting_level=512'; \
        echo 'xdebug.log_level=7'; \
        echo 'xdebug.log=/tmp/xdebug.log'; \
    } > /usr/local/etc/php/conf.d/zz-xdebug.ini

# 4) PHP settings for Moodle + Apache document root on Moodle 5.x /public.
#    opcache.revalidate_freq=0 guarantees every request sees the latest saved
#    source in src/ — no implicit staleness window, no restart/purge needed.
RUN { \
        echo 'upload_max_filesize = 128M'; \
        echo 'post_max_size = 128M'; \
        echo 'max_execution_time = 300'; \
        echo 'max_input_vars = 5000'; \
        echo 'memory_limit = 512M'; \
        echo 'opcache.revalidate_freq = 0'; \
        echo 'log_errors = On'; \
        echo 'error_log = /var/log/apache2/error.log'; \
    } > /usr/local/etc/php/conf.d/zz-moodle.ini \
    && a2enmod rewrite expires headers \
    && sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' \
        /etc/apache2/sites-available/000-default.conf \
    && sed -i 's|<Directory /var/www/html>|<Directory /var/www/html/public>|g' \
        /etc/apache2/apache2.conf \
    && printf 'ServerName localhost\n' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

# 5) Run Apache as the host user so the mounted plugin (and moodledata) stay
#    writable from the editor, the dev container and the web server alike.
#    -o allows re-using an id that already exists in the base image.
RUN set -eux; \
    groupmod -o -g "${APP_GID}" www-data; \
    usermod  -o -u "${APP_UID}" -g "${APP_GID}" www-data; \
    mkdir -p /var/www/html /var/www/moodledata /var/www/phpunitdata; \
    chown -R www-data:www-data /var/www

# 6) Composer (used by `mdl phpunit-init` inside Moodle core).
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 7) Pinned, checksum-verified Moodle release → /opt/moodle-src.
RUN set -eux; \
    cd /tmp; \
    base="https://packaging.moodle.org/${MOODLE_BRANCH}/moodle-${MOODLE_VERSION}.tgz"; \
    curl -fsSL -o moodle.tgz "${base}"; \
    curl -fsSL -o moodle.tgz.sha256 "${base}.sha256"; \
    expected="$(sed -E 's/.*=[[:space:]]*//' moodle.tgz.sha256 | tr -d '[:space:]')"; \
    echo "${expected}  moodle.tgz" | sha256sum -c -; \
    mkdir -p /opt/moodle-src; \
    tar -xzf moodle.tgz -C /opt/moodle-src --strip-components=1; \
    rm -f moodle.tgz moodle.tgz.sha256; \
    echo "${MOODLE_BRANCH}/${MOODLE_VERSION}" > /opt/moodle-src/.moodle-version; \
    chown -R www-data:www-data /opt/moodle-src

# 8) Boot logic: core sync → install → upgrade → dev settings → Apache.
COPY .devcontainer/app-entrypoint.sh /usr/local/bin/app-entrypoint.sh
COPY .devcontainer/lib/dev-setup.php /usr/local/lib/ifthenpay/dev-setup.php
RUN chmod +x /usr/local/bin/app-entrypoint.sh

WORKDIR /var/www/html
ENTRYPOINT ["/usr/local/bin/app-entrypoint.sh"]
CMD ["apache2-foreground"]
