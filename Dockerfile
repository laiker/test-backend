FROM php:8.4-cli

ARG INSTALL_XDEBUG=true

# Install dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

# Install Xdebug (optional)
RUN if [ "$INSTALL_XDEBUG" = "true" ]; then \
      curl -fsSL https://github.com/php/pie/releases/latest/download/pie.phar -o /usr/local/bin/pie && \
      chmod +x /usr/local/bin/pie && \
      if pie install xdebug/xdebug; then \
        docker-php-ext-enable xdebug && \
        echo "xdebug.mode=debug,profile" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
        echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
        echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
        echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini; \
      else \
        echo "Skipping Xdebug installation (PIE failed)"; \
      fi; \
    fi

# PHP settings
RUN echo "memory_limit=256M" >> /usr/local/etc/php/conf.d/custom.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["tail", "-f", "/dev/null"]
