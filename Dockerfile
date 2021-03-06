# Start with the official Composer image and name it 'composer' for reference
FROM composer AS composer

# get the proper 'PHP' image from the official PHP repo at
FROM php:7.2-apache-stretch

# set the default values for mpm_prefork apache settings, overrideable through build arguments
ARG MPM_STARTSERVERS=5
ARG MPM_MINSPARESERVERS=5
ARG MPM_MAXSPARESERVERS=10
ARG MPM_MAXREQUESTWORKERS=150
ARG MPM_MAXCONNECTIONSPERCHILD=0

# copy the Composer PHAR from the Composer image into the apache-php image
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Now that all the 'FROM' values are set, set the maintainer
MAINTAINER Ilios Project Team <support@iliosproject.org>

ENV \
COMPOSER_HOME=/tmp \
SYMFONY_ENV=prod \
ILIOS_DATABASE_HOST=db \
ILIOS_DATABASE_PORT=~ \
ILIOS_DATABASE_NAME=ilios \
ILIOS_DATABASE_USER=ilios \
ILIOS_DATABASE_PASSWORD=ilios \
ILIOS_DATABASE_MYSQL_VERSION=5.7 \
ILIOS_MAILER_TRANSPORT=smtp \
ILIOS_MAILER_HOST=127.0.0.1 \
ILIOS_MAILER_USER=~ \
ILIOS_MAILER_PASSWORD=~ \
ILIOS_LOCALE=en \
ILIOS_SECRET=ThisTokenIsNotSoSecretChangeIt \
ILIOS_AUTHENTICATION_TYPE=form \
ILIOS_LEGACY_PASSWORD_SALT=null \
ILIOS_FILE_SYSTEM_STORAGE_PATH=/data \
ILIOS_INSTITUTION_DOMAIN=example.com \
ILIOS_SUPPORTING_LINK=null \
ILIOS_LDAP_AUTHENTICATION_HOST=null \
ILIOS_LDAP_AUTHENTICATION_PORT=null \
ILIOS_LDAP_AUTHENTICATION_BIND_TEMPLATE=null \
ILIOS_LDAP_DIRECTORY_URL=null \
ILIOS_LDAP_DIRECTORY_USER=null \
ILIOS_LDAP_DIRECTORY_PASSWORD=null \
ILIOS_LDAP_DIRECTORY_SEARCH_BASE=null \
ILIOS_LDAP_DIRECTORY_CAMPUS_ID_PROPERTY=null \
ILIOS_LDAP_DIRECTORY_USERNAME_PROPERTY=null \
ILIOS_SHIBBOLETH_AUTHENTICATION_LOGIN_PATH=null \
ILIOS_SHIBBOLETH_AUTHENTICATION_LOGOUT_PATH=null \
ILIOS_SHIBBOLETH_AUTHENTICATION_USER_ID_ATTRIBUTE=null \
ILIOS_TIMEZONE='America/Los_Angeles' \
# This is for development, so SSL should be set to false by default \
ILIOS_REQUIRE_SECURE_CONNECTION=false \
ILIOS_KEEP_FRONTEND_UPDATED=true \
ILIOS_FRONTEND_RELEASE_VERSION=null \
ILIOS_CAS_AUTHENTICATION_SERVER=null \
ILIOS_CAS_AUTHENTICATION_VERSION=3 \
ILIOS_CAS_AUTHENTICATION_VERIFY_SSL=false \
ILIOS_CAS_AUTHENTICATION_CERTIFICATE_PATH=null \
ILIOS_ENABLE_TRACKING=false \
ILIOS_TRACKING_CODE=UA-XXXXXXXX-1 \
# Apache mpm_prefork modules are set as arguments above and can be overridden at build-time with command line arguments
# (eg, `docker build --build-arg "MPM_STARTSERVERS=20" --build-arg "MPM_MAXCONNECTIONSPERCHILD=4500")
MPM_STARTSERVERS=${MPM_STARTSERVERS} \
MPM_MINSPARESERVERS=${MPM_MINSPARESERVERS} \
MPM_MAXSPARESERVERS=${MPM_MAXSPARESERVERS} \
MPM_MAXREQUESTWORKERS=${MPM_MAXREQUESTWORKERS} \
MPM_MAXCONNECTIONSPERCHILD=${MPM_MAXCONNECTIONSPERCHILD}

# configure Apache and the PHP extensions required for Ilios and delete the source files after install
RUN \
    apt-get update \
    && apt-get install -y \
    && apt-get install libldap2-dev -y \
    && apt-get install zlib1g-dev \
    && docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ \
    && docker-php-ext-install ldap \
    && docker-php-ext-install zip \
    && docker-php-ext-install pdo_mysql \
    # enable mod_rewrite
    && mv /etc/apache2/mods-available/rewrite.load /etc/apache2/mods-enabled/ \
    && mv /etc/apache2/mods-available/socache_shmcb.load /etc/apache2/mods-enabled/ \
    # set up the mpm prefork module
    && mv /etc/apache2/mods-available/mpm_prefork.* /etc/apache2/mods-enabled/ \
    && sed -i -e 's|StartServers\s.*[0-9].*$|StartServers\t\t'"$MPM_STARTSERVERS"'|g' \
        /etc/apache2/mods-enabled/mpm_prefork.conf \
    && sed -i -e 's|MinSpareServers\s.*[0-9].*$|MinSpareServers\t\t'"$MPM_MINSPARESERVERS"'|g' \
        /etc/apache2/mods-enabled/mpm_prefork.conf \
    && sed -i -e 's|MaxSpareServers\s.*[0-9].*$|MaxSpareServers\t\t'"$MPM_MAXSPARESERVERS"'|g' \
        /etc/apache2/mods-enabled/mpm_prefork.conf \
    && sed -i -e 's|MaxRequestWorkers\s.*[0-9].*$|MaxRequestWorkers\t'"$MPM_MAXREQUESTWORKERS"'|g' \
        /etc/apache2/mods-enabled/mpm_prefork.conf \
    && sed -i -e 's|MaxConnectionsPerChild\s.*[0-9].*$|MaxConnectionsPerChild\t'"$MPM_MAXCONNECTIONSPERCHILD"'|g' \
        /etc/apache2/mods-enabled/mpm_prefork.conf \
    # remove the apt source files to save space
    && rm -rf /var/lib/apt/lists/* \
    && pecl channel-update pecl.php.net \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && mv /etc/apache2/sites-enabled/000-default.conf /etc/apache2/sites-enabled/ilios.conf \
    && mv /etc/apache2/conf-available/docker-php.conf /etc/apache2/conf-enabled/ \
    # allow httpd overrides in the /var/www/ilios/web directory
    && sed -i -e 's|/var/www|/var/www/ilios/web|g' /etc/apache2/conf-enabled/docker-php.conf \
    # update the DocumentRoot to point to the '/var/www/ilios/web' directory
    && sed -i -e 's|/var/www/html|/var/www/ilios/web|g' /etc/apache2/sites-enabled/ilios.conf \
    # and finally, create the php.ini file...
    && echo "\
[PHP]\n\
engine = On\n\
short_open_tag = Off\n\
precision = 14\n\
output_buffering = 4096\n\
zlib.output_compression = Off\n\
implicit_flush = Off\n\
serialize_precision = -1\n\
zend.enable_gc = On\n\
expose_php = Off\n\
max_execution_time = 300\n\
max_input_time = 60\n\
memory_limit = 1G\n\
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT\n\
display_errors = Off\n\
display_startup_errors = Off\n\
log_errors = On\n\
log_errors_max_len = 1024\n\
ignore_repeated_errors = Off\n\
ignore_repeated_source = Off\n\
report_memleaks = On\n\
html_errors = On\n\
variables_order = \"GPCS\"\n\
request_order = \"GP\"\n\
register_argc_argv = Off\n\
auto_globals_jit = On\n\
post_max_size = 105M\n\
default_mimetype = \"text/html\"\n\
default_charset = \"UTF-8\"\n\
file_uploads = On\n\
upload_max_filesize = 105M\n\
max_file_uploads = 20\n\
allow_url_fopen = On\n\
allow_url_include = Off\n\
default_socket_timeout = 60\n" > /usr/local/etc/php/php.ini

# create the volume that will store the learning materials
VOLUME /data

# copy the contents of the current directory to the /var/www/ilios directory
COPY . /var/www/ilios

# add all the extra directories necessary for the application
RUN \
    mkdir -p \
    /var/www/ilios/var \
    /var/www/ilios/var/cache \
    /var/www/ilios/var/logs \
    /var/www/ilios/var/session \
    /var/www/ilios/var/tmp \
    /var/www/ilios/vendor \
    # set up logging to STDOUT for production
    && sed -i -e 's|type:.*fingers_crossed|type:         error_log|g' /var/www/ilios/app/config/config_prod.yml \
    && sed -i -e 's|action_level:.*error|action_level: debug|g' /var/www/ilios/app/config/config_prod.yml \
    && sed -i -e "s|path:.*'%kernel.logs_dir%/%kernel.environment%.log'|path:  \"php://stdout\"|g" \
        /var/www/ilios/app/config/config_prod.yml \
    # recursively change user/group ownership of the app root to 'www-data'
    && chown -R www-data:www-data /var/www/ilios \
    # give the www-data user a temporary shell in order to build the Ilios app
    && chsh -s /bin/bash www-data

# change to the context of the 'www-data' user
USER www-data

WORKDIR /var/www/ilios

# as the 'www-data' user, build the app using composer and then remove it
RUN \
    /usr/bin/composer install \
    --working-dir /var/www/ilios \
    --prefer-dist \
    --no-dev \
    --no-progress \
    --no-interaction \
    --no-suggest \
    --classmap-authoritative \
    && /usr/bin/composer clear-cache \
    && /var/www/ilios/bin/console cache:clear --env=prod \
    && /var/www/ilios/bin/console cache:warmup --env=prod

# switch back to the root user to finish up
USER root

# revert the 'www-data' user's shell to its default
RUN \
    chsh -s /usr/sbin/nologin www-data

# launch apache httpd as a foreground service
CMD ["apache2-foreground"]

# http is typically port 80
EXPOSE 80
