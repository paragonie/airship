# CMS Airship Release Cycle

We will be strictly adhering to [semantic versioning](http://semver.org).

## Major Releases

CMS Airship releases a major version each year that pins its minimum PHP
version requirements to the latest version available.

| PHP Version | Airship Version | Release Date | End of Support |
|-------------|-----------------|--------------|----------------|
| 7.0.x       | 1.x.y           | 2016-06-27   | 2019-06-26     |
| 7.1.x       | 2.x.y           | 2017-01-01   | 2019-12-31     |  
| 7.2.x       | 3.x.y           | 2018-01-01   | 2020-12-31     |

We aim to release a new major version of CMS Airship once PHP releases a new
minor version. If a version of PHP has reached end-of-life, the corresponding
version of CMS Airship will cease to receive free support from Paragon
Initiative Enterprises. Paid support is available for companies that cannot
upgrade due to contractual obligations or compliance requirements.

## Minor Releases

Minor releases are anticipated to occur quarterly. They should receive most of
the new features being implemented in the development branch for the next major
version of Airship (when backwards compatibility permits).

Minor releases should not break backwards compatibility with our public API
features. Cabins and Motifs developed for 1.0.0 should still work in 1.12.0.

If a BC break must occur to prevent a security-affecting bug, we will do so in
a minor version rather than a patch version, and document the BC break.

## Patch Releases

Patch releases are not scheduled. They will be released as-needed and installed
automatically. Should any security bugs crop up, the relevant patch will be
released as a bump in minor version.

Patch release should contain:

* Security fixes
* Usability fixes
* Performance fixes

Patch releases *MAY* contain:

* Update code meant to nuke a Supplier's existing public keys from the trust
  store, in the event of a catastrophe where the legitimate Supplier has lost
  access to their signing keys. These incidents will be discussed publicly on
  the [Airship repository](https://github.com/paragonie/airship) under the tag,
  [**nuclear option key replacement**](https://github.com/paragonie/airship/labels/nuclear%20option%20key%20replacement).
* Alter database schemas (i.e. add a column or table, never delete). These
  types of changes should be rare.

## Long-Term Support Contracts

If three years is too short for your organization, get in touch with
[Paragon Initiative Enterprises](https://paragonie.com/contact). Long term 
support contracts only address security fixes. We will not be offering long
term support to the general public, as one of our design goals was to ensure
everyone always runs the latest version.
