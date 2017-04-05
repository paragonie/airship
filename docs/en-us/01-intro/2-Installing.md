# Installing Airship

## Easy Mode

There's a docker-compose.yml file in [the main repo](https://github.com/paragonie/airship) that used along side with
docker-compose will build and run the necessary software. See [the official docker documentation](https://docs.docker.com/compose/overview/) for details on how to utilize the docker-compose.

## Manual Installation

> Note: If you have trouble verifying GPG signatures, you might need to
> [change the GPG library path](http://jotham-city.com/blog/2015/02/14/verifying-gpg-signatures-for-makepkg/).

**Requirements**:

* PHP 7.0 or newer
  * In general, try to always run the latest version available.
* PostgreSQL 9.5 or newer
* libsodium 1.0.9 or newer
  * (Not released as of this writing)
* ext/sodium 1.0.6 or newer
  * You can install it from PECL after libsodium is installed
* ext/curl
* ext/gd
* ext/json
* ext/mbstring
* ext/pgsql
* ext/zip

**Optional**:

* If you have Tor installed, your Airship can proxy all network requests over
  the Tor network, thus preventing your server's IP address from being
  revealed.
* If your version of PHP doesn't ship with the JSON extension, you must install
  that separately.

## The General Install Workflow

1. Install PHP 7
2. Install PostgreSQL 9.5 and ext/pgsql
3. Install libsodium (may require compiling it manually)
4. Install ext/sodium (requires PECL)
5. Install ext/curl
6. Configure/restart your webserver
7. Download CMS Airship
8. Visit your Airship's URL to finish the process

# Installing on GNU/Linux

## Debian Jessie

(These steps assume a freshly installed Debian system. You may be able to skip some steps.)

First, you'll need the [DotDeb repository](https://www.dotdeb.org/instructions/) as well as the PostgreSQL repository.

### PHP 7 (+ Basic Extensions)

Run these commands to get PHP 7 installed. These instructions assume you have Ubuntu 16.04 or Debian Jessie [with the dotdeb repository](https://www.dotdeb.org/instructions/) already set up:

    # Let's get, and verify, the correct PGP public key
    gpg --fingerprint 6572BBEF1B5FF28B28B706837E3F070089DF5277
    if [ $? -ne 0 ]; then
        echo -e "\033[33mDownloading PGP Public Key...\033[0m"
        gpg --recv-keys 6572BBEF1B5FF28B28B706837E3F070089DF5277
        # http://pgp.mit.edu/pks/lookup?op=vindex&fingerprint=on&search=0x6572BBEF1B5FF28B28B706837E3F070089DF5277
        # DotDeb Signing Key 
        gpg --fingerprint 6572BBEF1B5FF28B28B706837E3F070089DF5277
        if [ $? -ne 0 ]; then
            echo -e "\033[31mCould not download PGP public key for verification\033[0m"
            exit
        fi
    fi
    gpg -a --export 6572BBEF1B5FF28B28B706837E3F070089DF5277 | sudo apt-key add -
    
    # Install PHP from DotDeb
    sudo apt-get -y install php7.0 php7.0-cli php7.0-fpm php7.0-json php7.0-pgsql php7.0-curl php7.0-dev php7.0-mbstring php7.0-gd
    wget https://pear.php.net/go-pear.phar
    
    # The PEAR team doesn't provide a GPG signature, so we have to do this:
    echo "8322214a6979a0917f0068af924428a80ff7083b94343396b13dac1d0f916748025fab72290af340d30633837222c277  go-pear.phar" | sha384sum -c
    if [ $? -eq 0 ]; then
        php go-pear.phar
    fi
    
    sudo pecl install zip
    echo "extension=zip.so" > /etc/php/7.0/cli/conf.d/20-zip.ini
    echo "extension=zip.so" > /etc/php/7.0/fpm/conf.d/zip.ini

### PostgreSQL

    echo "deb https://apt.postgresql.org/pub/repos/apt/ jessie-pgdg main" >> /etc/apt/sources.list
    # Let's get, and verify, the correct PGP public key
    gpg --fingerprint B97B0AFCAA1A47F044F244A07FCC7D46ACCC4CF8
    if [ $? -ne 0 ]; then
        echo -e "\033[33mDownloading PGP Public Key...\033[0m"
        gpg --recv-keys B97B0AFCAA1A47F044F244A07FCC7D46ACCC4CF8
        # http://pgp.mit.edu/pks/lookup?op=vindex&fingerprint=on&search=0xB97B0AFCAA1A47F044F244A07FCC7D46ACCC4CF8
        # PostgreSQL Signing Key 
        gpg --fingerprint B97B0AFCAA1A47F044F244A07FCC7D46ACCC4CF8
        if [ $? -ne 0 ]; then
            echo -e "\033[31mCould not download PGP public key for verification\033[0m"
            exit
        fi
    fi
    gpg -a --export B97B0AFCAA1A47F044F244A07FCC7D46ACCC4CF8 | sudo apt-key add -
    
    # Now. let's install PostgreSQL
    sudo apt-get update
    sudo apt-get install postgresql-9.5

### Libsodium

Next, you will need to [install libsodium and ext/sodium](https://paragonie.com/book/pecl-libsodium/read/00-intro.md#installing-libsodium).
Something like these commands ought to do the trick.

    git clone https://github.com/jedisct1/libsodium.git
    cd libsodium
    git checkout tags/1.0.10
    ./autogen.sh
    ./configure && make distcheck
    sudo make install
    sudo pecl install libsodium
    # Now grab the PECL extension
    sudo phpenmod libsodium

### Your Webserver

#### Caddy (Recommended)

The easiest webserver (and likely the most secure) is [Caddy](https://caddyserver.com).
One of its main advantages is automatic HTTPS integration with the certificate
authority, [LetsEncrypt](https://letsencrypt.org).

Refer to the [Caddy quick start](https://github.com/mholt/caddy#quick-start) to
set up Caddy and [Running Caddy in production](https://github.com/mholt/caddy#running-in-production).

Your Caddyfile should look like [the example](example-config/Caddyfile).
Feel free to customize it to meet your needs. It assumes PHP7-FPM.

#### Nginx

Refer to the [nginx documentation](http://nginx.org/en/docs/install.html).
You should be able to get away with:

    sudo apt-get install php7.0-fpm nginx

Your virtual host configuration file should look like [the example](example-config/nginx.conf).
Feel free to customize it to meet your needs.

You'll want to set your document root to the `src/public` subdirectory
of your Airship.

#### Apache

Refer to the [Apache documentation](https://httpd.apache.org/docs/current/install.html).
You should be able to get away with:

    sudo apt-get install php7.0 apache2

You'll want to set your document root to the `src/public` subdirectory
of your Airship.

### Installing CMS Airship

Once your webserver is setup and configured, you're ready to begin installing
CMS Airship.

Use Git and Composer to obtain [the latest release](https://github.com/paragonie/airship/releases).

    cd /var/www/
    git clone https://github.com/paragonie/airship.git
    cd airship
    git checkout v1.4.2
    composer install

Since Airship self-updates, it needs to be able to write to itself.

    chown -R myusername:www-data airship
    chmod -R g+w airship

If you don't already have a PostgreSQL database set up:

    sudo su postgres -c "createuser airship -P"
    # You will be prompted for a password twice.
    sudo su postgres -c "createdb -O airship airship"

If you haven't already done so, restart your webserver then visit the URL or IP
address that corresponds to the active virtual host in your browser.

Once you access the web installer, a security cookie is placed in your browser
which prevents anyone from accessing the installer until the process is 
finished. If you get locked out, run this command and reload
the page. (You will have to start over, but the process is brief.)

    php src/Installer/launch.php reset
    
From this point, follow the prompts on the web-based installer and you'll be
ready to take off.

[Next: Basic Usage](https://github.com/paragonie/airship-docs/tree/master/en-us/02-basic-usage).
