# Airship - Content Management Platform

*The sky is only the beginning.*

[![Build Status](https://travis-ci.org/paragonie/airship.svg?branch=master)](https://travis-ci.org/paragonie/airship)

Airship is a content management/blogging platform and application development
framework written for PHP 7. Requires [libsodium](https://download.libsodium.org/doc/).

Airship is [Free Software](https://github.com/paragonie/airship/blob/master/LICENSE.txt)
(GPL 3) developed and maintained by [Paragon Initiative Enterprises](https://paragonie.com).

[Commercial license are available for purchase](https://paragonie.com/contact)
if your company requires one.

## Minimum Requirements

* PHP 7.0.5 or newer
* PECL Libsodium 1.0.6 or newer
* Libsodium 1.0.10 or newer

## Documentation

See [paragonie/airship-docs](https://github.com/paragonie/airship-docs).

## Customizing Your Airship

Airship extensions come in three flavors:

* **Cabins**: entire self-contained applications
* **Gadgets**: alters the functionality of an existing cabin (or of the
  Engine itself)
* **Motifs**: alters the apperance of an existing cabin

To create and/or manage these extensions, check out 
[barge, our command line utility](https://github.com/paragonie/airship-barge).