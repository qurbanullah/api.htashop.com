FROM ubuntu:24.04

# Install PHP repository and packages in one layer
RUN apt-get update && \
    apt-get install -y software-properties-common apt-transport-https ca-certificates && \
    add-apt-repository ppa:ondrej/php -y && \
    apt-get update && \
    apt-get install -y \
        apache2 \
        php8.4 php8.4-common php8.4-cli php8.4-fpm \
        php8.4-dom php8.4-gd php8.4-mbstring php8.4-xml php8.4-intl \
        php8.4-curl php8.4-gmp php8.4-bcmath php8.4-posix php8.4-zip \
        php8.4-redis php8.4-phar php8.4-ctype php8.4-opcache \
        php8.4-tokenizer php8.4-fileinfo php8.4-iconv php8.4-mysql php8.4-bz2 php8.4-imagick \
        php8.4-sqlite3 sqlite3 libsqlite3-dev \
        sudo mariadb-client git curl supervisor && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Configure Apache with mpm_event + PHP-FPM in one layer
RUN a2dismod mpm_prefork && \
    a2enmod mpm_event rewrite headers expires proxy proxy_fcgi && \
    a2enconf php8.4-fpm && \
    echo "Listen 20050" >> /etc/apache2/ports.conf && \
    ln -sf /dev/stdout /var/log/apache2/access.log && \
    ln -sf /dev/stderr /var/log/apache2/error.log

# Configure php-fpm to listen on a unix socket and ensure socket ownership/mode
# We prefer a unix socket in production for better performance and predictable
# filesystem permissions. Keep listen.owner/group/mode explicit so Apache can
# reliably connect.
RUN sed -i "s|^listen = .*|listen = /run/php/php8.4-fpm.sock|" /etc/php/8.4/fpm/pool.d/www.conf || true && \
    # Ensure ownership and mode are explicit
    grep -q "^listen.owner" /etc/php/8.4/fpm/pool.d/www.conf || echo "listen.owner = www-data" >> /etc/php/8.4/fpm/pool.d/www.conf && \
    grep -q "^listen.group" /etc/php/8.4/fpm/pool.d/www.conf || echo "listen.group = www-data" >> /etc/php/8.4/fpm/pool.d/www.conf && \
    grep -q "^listen.mode" /etc/php/8.4/fpm/pool.d/www.conf || echo "listen.mode = 0660" >> /etc/php/8.4/fpm/pool.d/www.conf && \
    # Ensure the runtime directory exists and has correct ownership so php-fpm
    # can create the socket with the right owner/group; chown in image build
    mkdir -p /run/php && chown -R www-data:www-data /run/php || true

# Copy application and install dependencies
# Set working directory
WORKDIR /var/www/html

# Copy existing application directory permissions
COPY --chown=www-data:www-data . /var/www/html

# Ensure production env for Docker is present in the image (copied from .env.docker)
# .env is intentionally created from .env.docker so container has defaults when runtime env not provided.
COPY .env.docker /var/www/html/.env

# Change ownership of the storage and cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Change permissions of the storage and cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Laravel permission issue fixes
# RUN chown -R www-data:www-data /var/www/html && \
#     chmod -R 755 /var/www/html && \
#     chmod -R 775 storage bootstrap/cache

# install composer and project dependencies
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --optimize-autoloader --no-dev --no-interaction --no-progress
# Force regeneration of autoload files to ensure they're up to date
RUN composer dump-autoload --optimize

# Copy configurations and enable performance settings
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/apache-performance.conf /etc/apache2/conf-available/performance.conf
COPY docker/php.ini /etc/php/8.4/fpm/conf.d/99-custom.ini
RUN a2enconf performance && \
    a2ensite 000-default

# Set Laravel permissions and create required directories
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod -R 775 storage bootstrap/cache

# Ensure www-data group exists and create non-root runtime user 'ubuntu' idempotently
# Use defensive checks to avoid UID/GID conflicts on different base images
RUN getent group www-data >/dev/null 2>&1 || groupadd -r www-data && \
    (getent group ubuntu >/dev/null 2>&1 || groupadd ubuntu) && \
    (id -u ubuntu >/dev/null 2>&1 || useradd -m -s /bin/bash -g ubuntu ubuntu) && \
    usermod -a -G www-data ubuntu >/dev/null 2>&1 || true && \
    mkdir -p /var/run/apache2 /var/lock/apache2 /tmp && \
    chown -R ubuntu:www-data /var/run/apache2 /var/lock/apache2 /var/www/html /tmp && \
    # Allow ubuntu to elevate via sudo without password for controlled root actions
    echo 'ubuntu ALL=(ALL) NOPASSWD:ALL' > /etc/sudoers.d/ubuntu && \
    chmod 0440 /etc/sudoers.d/ubuntu

EXPOSE 20050

# Ensure our entrypoint is present and executable
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Ensure runtime directories and php log exist and are writable by the runtime user
# supervisord will run as root (default) so it can correctly start services that
# need to create sockets; supervisord is configured to spawn apache/php-fpm as
# the non-root user `ubuntu`.
RUN mkdir -p /run/php /var/run/apache2 /var/log && \
    touch /var/log/php8.4-fpm.log || true && \
    chown -R ubuntu:www-data /run/php /var/run/apache2 /var/log/php8.4-fpm.log /var/www/html || true && \
    chmod 775 /run/php /var/run/apache2 || true && \
    chmod 664 /var/log/php8.4-fpm.log || true

# Run container as non-root 'ubuntu' and allow passwordless sudo for needed root tasks.
# Supervisord will be started via sudo in CMD so supervisord runs as root while the container user is non-root.
USER ubuntu

ENTRYPOINT ["/entrypoint.sh"]
CMD ["sudo", "/usr/bin/supervisord", "-n"]
