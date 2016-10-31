# How to Contribute

Please make pull requests against the `master` branch. If we need to backport a
bugfix into a different branch, we will take care of that.

If you would like to improve the documentation, you want the 
[documentation repository](https://github.com/airship-docs).

## Current Branches

* `master` is the future home of `v2.x` when PHP 7.1 is released. All active
  development must be against the `master` branch. Changes will be backported
  when appropriate.
* `v1.x` is the home of the `v1.x` branch.
  * Bug fixes will be backported from `master` to `v1.x` during patch releases.
  * UI improvements will be held until the next minor relase.

## Security Issues

If you believe you have found a security issue in CMS Airship, please
feel free to report it via [full disclosure](https://en.wikipedia.org/wiki/Full_disclosure_%28computer_security%29).
The [Full Disclosure mailing list](http://seclists.org/fulldisclosure/) is a good place to announce your findings.

Alternatively, feel free to create a Github issue or [participate in our HackerOne bug bounty program](https://hackerone.com/paragonie).

If you would feel more comfortable disclosing vulnerables privately, our
security team has a [GnuPG public key](https://paragonie.com/static/gpg-public-key.txt) (for **`security@paragonie.com`**).

```txt
-----BEGIN PGP PUBLIC KEY BLOCK-----
Version: SKS 1.1.5

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
=Z4m0
-----END PGP PUBLIC KEY BLOCK-----
```

## Financial Contributions

### Commercial Licenses

If your company wants to use CMS Airship to develop non-free software,
you will need to purchase a commercial license from Paragon Initiative
Enterprises, LLC. Please use [our contact form](https://paragonie.com/contact)
to request a **Commercial License Inquiry**.

### Direct Financial Contributions

If you'd like to directly make a financial contribution towards Airship 
development, please send ZCash to the address below.

```
zcCFWNGRZxT6fB82eiSXrx2NDFrgKL2kyissQX73dyWgLB2HoTYMuMYzCgVsKcLZ1iUrcTWnx8vwWfEBQd2WDavpYtJLFrw
```

Any funds received at that ZCash address will be used to subsidize our
development costs, including the cost of third party consultants (i.e.
for user interface/experience expertise).
