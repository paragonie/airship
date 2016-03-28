# Installing Airship

**Requirements**:

* PHP 7.0.4 or newer
  * In general, try to always run the latest version available.
* PostgreSQL 9.5 or newer
* libsodium 1.0.9 or newer
  * (Not released as of this writing)
* ext/sodium 1.0.3 or newer
  * You can install it from PECL after libsodium is installed
* ext/curl
* ext/json
* ext/pgsql

**Optional**:

* If you have Tor installed, your Airship can proxy all network requests over the Tor network, thus preventing your server's IP address from being revealed.
* If your version of PHP doesn't ship with the JSON extension, you must install that separately.

## The General Install Workflow

1. Install PHP 7
2. Install PostgreSQL 9.5 and ext/pgsql
3. Install libsodium (may require compiling it manually)
4. Install ext/sodium (requires PECL)
5. Install ext/curl
6. Configure/restart your webserver
7. Visit your Airship's URL to finish the process

# Installing on GNU/Linux

## Debian Jessie

(These steps assume a freshly installed Debian system. You may be able to skip some steps.)

First, you'll need the [DotDeb repository](https://www.dotdeb.org/instructions/) as well as the PostgreSQL repository.

### PHP 7 (+ Basic Extensions)

Run these commands to get PHP 7 installed:

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
    sudo apt-get -y install php7.0 php7.0-cli php7.0-fpm php7.0-json php7.0-pgsql php7.0-curl php7.0-dev
    sudo wget https://pear.php.net/go-pear.phar
    
    # The PEAR team doesn't provide a GPG signature, so we have to do this:
    echo "8322214a6979a0917f0068af924428a80ff7083b94343396b13dac1d0f916748025fab72290af340d30633837222c277  go-pear.phar" | sha384sum -c
    if [ $? -eq 0 ]; then
        php go-pear.phar
    fi

### PostgreSQL

    deb http://apt.postgresql.org/pub/repos/apt/ jessie-pgdg main
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

> **Note**: As of right now, libsodium 1.0.9 has not been released.

Next, you will need to [install libsodium and ext/sodium](https://paragonie.com/book/pecl-libsodium/read/00-intro.md#installing-libsodium). Something like these commands ought to do the trick.

    git clone https://github.com/jedisct1/libsodium.git
    cd libsodium
    git checkout tags/1.0.9
    ./autogen.sh
    ./configure && make distcheck
    sudo make install
    sudo pecl install libsodium
    # Now grab the PECL extension
    sudo phpenmod libsodium

### Your Webserver

#### Caddy (Recommended)

TODO: Explain how to install/configure [Caddy Server](https://caddyserver.com), which already offers LetsEncrypt integration.

#### Nginx

TODO: Explain how to install/configure nginx with LetsEncrypt integration.

#### Apache

TODO: Explain how to install/configure Apache with LetsEncrypt integraiton.