# Instalando Airship

## Modo Fácil

Hay un archivo docker-compose.yml en [el repositorio principal](https://github.com/paragonie/airship) que usado junto con
docker-compose construirá el software requerido. Leer [la documentaciòn oficial de docker](https://docs.docker.com/compose/overview/) para màs detalles sobre còmo utilizar docker-compose.

## Instalación Manual 

> Nota: Si tiene problemas al verificar las firmas GPG, puede que necesite
> [cambiar la ruta de PGP](http://jotham-city.com/blog/2015/02/14/verifying-gpg-signatures-for-makepkg/).

**Requisitos**:

* PHP 7.0 o superior
  * En general, intente siempre tener la última versión.
* PostgreSQL 9.5 or superior
* libsodium 1.0.9 or superior
  * (No se ha lanzado hasta la fecha de escritura)
* ext/sodium 1.0.6 or superior
  * Puede instalarlo desde PECL una vez que libsodium se ha instalado
* ext/curl
* ext/gd
* ext/json
* ext/mbstring
* ext/pgsql
* ext/zip

**Opcional**:

* Si tiene Tor instalado, su Airship puede emplear un proxy para todas las peticiones
  mediante la red Tor, y así prevenir que se revele la dirección IP de su
  servidor.
* Si su versión de PHP no viene con la extensión JSON instalada, deberá instalarla
  por separado.

## El procedimiento de instalación en general

1. Instalar PHP 7
2. Instalar PostgreSQL 9.5 y ext/pgsql
3. Instalar libsodium (puede que necesite compilarlo manualente)
4. Instalar ext/sodium (requiere PECL)
5. Instalar ext/curl
6. Configurar/reiniciar su webserver
7. Descargar CMS Airship
8. Visite la  URL de Airship's para finalizar el proceso

# Instalar en GNU/Linux

## Debian Jessie

(Estos pasos asumen que acaba de instalar un sistema Debian. Puede saltarse algunos.)

Primero necesitará el [repositorio DotDeb](https://www.dotdeb.org/instructions/) junto con el repositorio PostgreSQL.

### PHP 7 (+ Extensiones Básicas)

Ejecute estos comandos para instalar PHP 7. Estas instrucciones asumn que tiene Ubuntu 16.04 o Debian Jessie [con el repositorio dotdeb](https://www.dotdeb.org/instructions/) ya configurado:

    # Obtengamos y verifiquemos la clave pública PGP correcta
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
    
    # Instalar PHP desde DotDeb
    sudo apt-get -y install php7.0 php7.0-cli php7.0-fpm php7.0-json php7.0-pgsql php7.0-curl php7.0-dev php7.0-mbstring php7.0-gd
    wget https://pear.php.net/go-pear.phar
    
    # PEAR team no provee una firma GPG, así que tendremos que hacer ésto:
    echo "8322214a6979a0917f0068af924428a80ff7083b94343396b13dac1d0f916748025fab72290af340d30633837222c277  go-pear.phar" | sha384sum -c
    if [ $? -eq 0 ]; then
        php go-pear.phar
    fi
    
    sudo pecl install zip
    echo "extension=zip.so" > /etc/php/7.0/cli/conf.d/20-zip.ini
    echo "extension=zip.so" > /etc/php/7.0/fpm/conf.d/zip.ini

### PostgreSQL

    echo "deb https://apt.postgresql.org/pub/repos/apt/ jessie-pgdg main" >> /etc/apt/sources.list
    # Obtengamos y verifiquemos la clave pública PGP correcta
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
    
    # Ahora, a instalar PostgreSQL
    sudo apt-get update
    sudo apt-get install postgresql-9.5

### Libsodium

A continuación tendrá que [instalar libsodium y ext/sodium](https://paragonie.com/book/pecl-libsodium/read/00-intro.md#installing-libsodium).
Estos comandos deberán funcionar.

    git clone https://github.com/jedisct1/libsodium.git
    cd libsodium
    git checkout tags/1.0.10
    ./autogen.sh
    ./configure && make distcheck
    sudo make install
    sudo pecl install libsodium
    # Now grab the PECL extension
    sudo phpenmod libsodium

### Su Webserver

#### Caddy (Recommendado)

El webserver más fácil (y probablemente el más secure) es [Caddy](https://caddyserver.com).
Una de sus ventajas principales es la integración automática con la autoridad de certificación HTTPS,
[LetsEncrypt](https://letsencrypt.org).

Refiérase a la [guía de inicio rápido de Caddy](https://github.com/mholt/caddy#quick-start) para
configuar Caddy y [Ejecutar Caddy en producción](https://github.com/mholt/caddy#running-in-production).

Su Caddyfile debería verse como [el ejemplo](example-config/Caddyfile).
Siéntase libre de modificarlo acorde a sus necesidades. Se asume PHP7-FPM.

#### Nginx

Refiérase a la [documentación nginx](http://nginx.org/en/docs/install.html).
Debería poder ejecutar:

    sudo apt-get install php7.0-fpm nginx

Su archivo de configuración del virtual host debería verse como [el ejemplo](example-config/nginx.conf).
Siéntase libre de modificarlo acorde a sus necesidades.

Querrá poner la raíz de su documento en el subdirectorio `src/public`
de su Airship.

#### Apache

Refiérase a la [documentación Apache](https://httpd.apache.org/docs/current/install.html).
Debería poder ejecutar:

    sudo apt-get install php7.0 apache2

Querrá poner la raíz de su documento en el subdirectorio `src/public`
de su Airship.

### Installing CMS Airship

Una vez que su webserver se ha configurado, estará listo para comenzar a instalar
CMS Airship.

Use Git y Composer para obtener [la última versión](https://github.com/paragonie/airship/releases).

    cd /var/www/
    git clone https://github.com/paragonie/airship.git
    cd airship
    git checkout v1.4.2
    composer install

Ya que Airship se actualiza a sí mismo, necesita tener permisos de escritura.

    chown -R myusername:www-data airship
    chmod -R g+w airship

si no tiene una base de datos PostgreSQL todavía, ejecute:

    sudo su postgres -c "createuser airship -P"
    # You will be prompted for a password twice.
    sudo su postgres -c "createdb -O airship airship"

Si no lo ha hecho todavía, reinicie su webserver y visite su URL o dirección IP
que corresponde al host virtual activo en su browser.

Una vez que haya accedido al instalador,una cookie de seguridad es puesta en su navegador,
la cual evita que cualquier otra persona entre al instalador hasta que el proceso haya 
terminado. Si por alguna razón ha quedado fuera, ejecute este comando y refresque
la página. (Tendrá que hacer todo de nuevo, pero el proceso no tarda mucho.)

    php src/Installer/launch.php reset
    
A partir de aquí, siga las instrucciones en el navegador y estará
listo para despegar.

[Siguiente: Uso Básico](https://github.com/paragonie/airship-docs/tree/master/en-us/02-basic-usage).
