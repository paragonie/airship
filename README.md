# CMS Airship - Secure Content Management for the Modern Web

[![Build Status](https://travis-ci.org/paragonie/airship.svg?branch=master)](https://travis-ci.org/paragonie/airship)
[![Latest Stable Version](https://poser.pugx.org/paragonie/airship/v/stable)](https://packagist.org/packages/paragonie/airship)
[![Latest Unstable Version](https://poser.pugx.org/paragonie/airship/v/unstable)](https://packagist.org/packages/paragonie/airship)
[![License](https://poser.pugx.org/paragonie/airship/license)](https://packagist.org/packages/paragonie/airship)

*The sky is only the beginning.*

CMS Airship is a **secure-by-default** content management system, blog
engine, and application development framework written for PHP 7.

CMS Airship is [Free Software](https://github.com/paragonie/airship/blob/master/LICENSE.txt)
(GPL 3) developed and maintained by [Paragon Initiative Enterprises](https://paragonie.com).

[Commercial license are available for purchase](https://paragonie.com/contact)
if your company requires an alternative to the GNU Public License.

## Benefits of CMS Airship

1. [**Digitally signed automatic security updates.**](https://github.com/paragonie/airship-docs/blob/master/en-us/WHY.md#1-digitally-signed-automatic-security-updates)
2. [Community first.](https://github.com/paragonie/airship-docs/blob/master/en-us/WHY.md#2-the-community-is-always-in-control-of-any-add-ons-it-produces)
   The community is always in control of any add-ons it produces. No one
   can backdoor your extensions without your signing keys.
3. [Supports a multi-site architecture out of the box.](https://github.com/paragonie/airship-docs/blob/master/en-us/WHY.md#3-supports-a-multi-site-architecture-out-of-the-box)
4. [Designed by progressive-minded application security professionals.](https://github.com/paragonie/airship-docs/blob/master/en-us/WHY.md#4-designed-by-progressive-minded-application-security-professionals)
5. [Fully customizable and extensible.](https://github.com/paragonie/airship-docs/blob/master/en-us/WHY.md#5-our-gear-system-allows-the-framework-to-be-extended)
   Our `Gears` system allows extensions to easily restructure and/or
   replace entire Airship features without causing conflicts with our
   secure automatic updating process.

See [how the out-of-the-box security of CMS Airship compares to WordPress, Drupal, or Joomla](https://paragonie.com/project/airship).

## Documentation

See [paragonie/airship-docs](https://github.com/paragonie/airship-docs).

# [Available on the AWS Marketplace](https://aws.amazon.com/marketplace/seller-profile?ref=cns_srchrow&id=139a5240-4d65-457b-81cf-6f13833a6ecd)

## Minimum Requirements

* PHP 7.0 or newer
* PECL Libsodium 1.0.6 or newer
* Libsodium 1.0.10 or newer

### Getting Started

 * [Five-minute overview of CMS Airship](https://github.com/paragonie/airship-docs/blob/master/en-us/5-Minute-Overview.md)
 * [Introduction](https://github.com/paragonie/airship-docs/tree/master/en-us/01-intro)
 * [How to install CMS Airship](https://github.com/paragonie/airship-docs/blob/master/en-us/01-intro/2-Installing.md)

## Customizing Your Airship

CMS Airship extensions come in three flavors ([detailed explanations](https://github.com/paragonie/airship-docs/blob/master/en-us/01-intro/1-Lingo-Jargon.md#airship-extension-types)):

* **Cabins**: self-contained applications
* **Gadgets**: alters the functionality of an existing Cabin (or of the
  Engine itself)
* **Motifs**: alters the apperance of an existing Cabin

To create and/or manage these extensions, check out 
[barge, our command line utility](https://github.com/paragonie/airship-barge).

### Screenshot

[![Screenshot](https://i.imgur.com/OYY5qmh.png)](https://cspr.ng)

Airship is fully mobile responsive thanks to the [Pure CSS framework](http://purecss.io/).
See it in action at [CSPR.NG](https://cspr.ng).

## Contributing to CMS Airship  

* See [CONTRIBUTING.md](https://github.com/paragonie/airship/blob/master/.github/CONTRIBUTING.md)
