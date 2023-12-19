ARG ALPINE_VERSION=3.16
FROM alpine:${ALPINE_VERSION}
#Args for after pulling alpine (need to be here, otherwise docker does not see them)
LABEL Maintainer="Lothar Husky110 Hoffmann"
LABEL Description="Lightweight container with Nginx & PHP 8.1 based on Alpine Linux. - based on https://github.com/TrafeX/docker-php-nginx"
# Setup document root
WORKDIR /var/www

# Install packages and remove default server definition
# TODO: I'm taking this from another project - maybe remove the unneccesary packages
RUN apk add --no-cache \
  aria2 \
  curl \
  nginx \
  php81 \
  php81-bcmath \
  php81-ctype \
  php81-curl \
  php81-dom \
  php81-fpm \
  php81-gd \
  php81-fileinfo \
  php81-intl \
  php81-mbstring \
  php81-opcache \
  php81-openssl \
  php81-pdo_sqlite \
  php81-phar \
  php81-session \
  php81-tokenizer \
  php81-xml \
  php81-xmlwriter \
  php81-zip \
  supervisor

# Create symlink so programs depending on `php` still function
RUN ln -s /usr/bin/php81 /usr/bin/php

# Installing composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer

# Configure nginx
RUN mkdir /nginx
COPY /files/nginx.conf /nginx/nginx.conf
COPY /files/nginx-password.conf /nginx/nginx-password.conf
ARG PASSNGINX=0
RUN if [[ "$PASSNGINX" = 0 ]]  ; then cp /nginx/nginx.conf /etc/nginx/nginx.conf ; else cp /nginx/nginx-password.conf /etc/nginx/nginx.conf; fi
RUN rm -rf /nginx

# Configure PHP-FPM
COPY /files/fpm-pool.conf /etc/php81/php-fpm.d/www.conf

# Configure supervisord
COPY /files/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

COPY --chown=1000:1000 /files/aria_scripts /aria_scripts

#bringing in the startup-script
COPY --chown=1000:1000 /files/startup.sh /startup.sh

# Setting up data-directory
RUN mkdir -p /data/checkpoints /data/loras /data/embeddings /download_tmp

# Make sure files/folders needed by the processes are accessable when they run under the nobody user
RUN chown -R 1000:1000 /var/www /run /var/lib/nginx /var/log/nginx /data /download_tmp

#Add Volumes
VOLUME /var/www /data/checkpoints /data/loras /data/embeddings
# Switch to use a non-root user from here on
USER 1000

# Expose the port nginx is reachable on
EXPOSE 8080

# Let supervisord start nginx & php-fpm
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# Configure a healthcheck to validate that everything is up&running
HEALTHCHECK --timeout=10s CMD curl --silent --fail http://127.0.0.1:8080/fpm-ping