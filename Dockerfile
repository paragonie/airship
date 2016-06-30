FROM ubuntu:16.04

RUN apt-get update
RUN apt-get -y upgrade
RUN apt-get -y install wget git composer postgresql php7.0 php7.0-cli \
                       php7.0-fpm php7.0-json php7.0-pgsql php7.0-curl \
                       php7.0-xml php7.0-dev php7.0-zip php7.0-mbstring \
                       composer apache2 libsodium-dev php-pear libapache2-mod-php7.0

RUN rm -f /etc/apache2/sites-enabled/000-default.conf
RUN a2enmod rewrite

RUN pecl install libsodium
RUN echo "extension=libsodium.so" > /etc/php/7.0/mods-available/libsodium.ini
RUN phpenmod libsodium

RUN a2enmod rewrite

RUN service postgresql start && sleep 3 && \
    su postgres -c "createuser airship" && \
    su postgres -c "createdb -O airship airship"

WORKDIR /var/www
RUN git clone https://github.com/paragonie/airship.git
WORKDIR /var/www/airship
RUN git checkout v1.0.2
RUN composer install --no-dev

RUN chown -R www-data:www-data .
RUN chmod -R g+w .

ENV CONF /etc/apache2/sites-enabled/airship.conf

RUN echo "<VirtualHost *:80>" > $CONF && \
    echo "DocumentRoot /var/www/airship/src/public" >> $CONF && \
    echo "ErrorLog ${APACHE_LOG_DIR}/error.log" >> $CONF && \
    echo "CustomLog ${APACHE_LOG_DIR}/access.log combined" >> $CONF && \
    echo "<Directory />" >> $CONF && \
    echo "RewriteEngine On" >> $CONF && \
    echo "RewriteCond %{REQUEST_FILENAME} -f [OR]" >> $CONF && \
    echo "RewriteCond %{REQUEST_FILENAME} -d" >> $CONF && \
    echo "RewriteRule (.*) - [L]" >> $CONF && \
    echo "RewriteRule (.*) /index.php?$1 [L]" >> $CONF && \
    echo "</Directory>" >> $CONF && \
    echo "</VirtualHost>" >> $CONF

ENV APACHE_RUN_USER www-data
ENV APACHE_RUN_GROUP www-data
ENV APACHE_PID_FILE /var/run/apache2/apache2.pid
ENV APACHE_RUN_DIR /var/run/apache2
ENV APACHE_LOCK_DIR /var/lock/apache2
ENV APACHE_LOG_DIR /var/log/apache2

CMD ["apache2", "-D", "FOREGROUND"]
