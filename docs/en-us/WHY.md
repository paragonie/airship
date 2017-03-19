## 5 Reasons to Use Airship Instead Of (any other platform)

1. **Digitally signed automatic security updates.**
2. The community is always in control of any add-ons it produces.
3. Supports a multi-site architecture out of the box.
4. Designed by progressive-minded application security professionals.
5. Our Gear system allows the framework to be extended.


### 1. Digitally signed automatic security updates.

Unlike other frameworks and content management systems, our authenticated 
automatic security updating mechanism is a **first-class design decision**.

If a security vulnerability is ever discovered in Airship, the patch 
will automatically be applied in your website within an hour of being
released by our team.

All security updates will be digitally signed with a secret key to 
guarantee authenticity; the associated public key is packaged with the
default Airship configuration. The digital signature algorithm we use is
**`Ed25519`** (facilitated by libsodium).

We take extra care when handling our secret key; should it ever be
compromised, we will use our backup key to revoke the old one and 
replace it with a new one.

You can disable the auto-update feature from the Bridge, but we do not
recommend doing this.

You can also choose to trust someone else's mirrors and public key 
instead of ours. The code is completely open, but you only need change a 
JSON configuration file to decide to trust someone else.

### 2. The community is always in control of any add-ons it produces.

Airship offers three strategies for extending its base features:

1. Cabins, which are entire applications (see #3 below).
2. Gadgets, which are plugins that can be applied at a per-Cabin level 
   or across every Cabin in your ship.
3. Motifs, which change the look and feel of your Airship. 

All Cabins, Gadgets, and Motifs can be assigned to a vendor (which has
its own Ed25519 key pair), and that supplier has control of the 
distribution of automatic updates.

**This gives you, the supplier, control over your add-ons**, not us. 
Neither the Airship development team nor Paragon Initiative Enterprises
can prevent your users from installing, updating, or using any add-on. 

We *can* still de-list abusive add-ons from the official SkyPort, but
anyone can operate their own and we will always aspire to make switching
to an alternative SkyPort as easy as pie.

Most importantly: Anyone can be a supplier; we don't believe in erecting
barriers to entry.

### 3. Supports a multi-site architecture out of the box.

Each Cabin is its own website. Install as many Cabins as you need. No 
questionable hacks needed.

### 4. Designed by progressive-minded application security professionals.

Airship was started by [Paragon Initiative Enterprises](https://paragonie.com).
We specialize in application security and applied cryptography.

### 5. Our Gear system allows the framework to be extended.

Because of our auto-updater, any local changes made to the Engine files 
will be obliterated whenever an upstream change occurs. To allow users
to extend and customize the core classes to meet their needs, we 
designed our application around the `Gears` system.

Most of the core `Engine` classes can be extended at runtime by the
extensions you create (or install from the community). Instead of
accessing the core classes directly, load the latest version of the Gear
(which could be our code, or yours).

## The Security Benefits of using Airship

Compare, for example, [this long guide to securing WordPress](https://codex.wordpress.org/Hardening_WordPress)
with our guide to securing Airship:

1. Use TLS (if you use [Caddy](https://github.com/paragonie/airship-docs/blob/master/en-us/01-intro/2-Installing.md#caddy-recommended),
   this is automatic in production environments).
2. Don't disable automatic updates.
3. Use a strong password.
   * and/or two-factor authentication

That's it. You don't need to jump through a dozen hoops to prevent your website
from being used by criminals to distribute malware or launch Distributed Denial
of Service attacks. Even if our infrastrucutre is compromised, your Airship is
protected by [strong cryptography](https://paragonie.com/blog/2016/05/keyggdrasil-continuum-cryptography-powering-cms-airship).

### Vulnerabilities we Prevent
 
What follows is a list of security vulnerabilities you will almost certainly
never have to worry about if you use CMS Airship.

* **Malicious File Uploads**
  * Airship uses a virtual filesystem that offers read-only access (and only
    to authorized users) to uploaded files. Files will never execute in the
    server nor in your browser.
* **SQL Injection** is effectively mitigated by our use of prepared 
  statements in nearly every context. Where prepared statements aren't
  used, a typecast to int or strict whitelist of allowed characters is
  enforced instead.
* **Insecure Session Management**
  * If you use HTTPS, all cookies are only sent over HTTPS.
  * Additionally, we support **Hypertext-Strict-Transport-Security** and
    **HTTP Public-Key-Pinning** out of the box.
* **Broken Authentication**
  * Airship uses state-of-the-art authentication protocols.
  * Airship rejects weak passwords that hackers could easily guess.
  * Passwords are never stored directly.
  * Account recovery tokens can be encrypted with your GnuPG public key.
  * Users can even *opt out* of the password reset feature entirely.
  * You cannout access any sensitive features without authorization.
* **Cross-Site Scripting** (XSS) is mitigated on two fronts:
  * **Output Escaping** (rather than *Input* escaping) practively
    prevents most XSS vulnerabilities from even occurring.
  * **Content-Security-Policy headers** act as a second line of defense
    for browsers. This is an exploit mitigation feature which should not
    be *relied* on. It's like a seatbelt for your passengers.
* **Insecure Direct Object Reference**
  * Our router is a whitelist, not a lazy match-maker.
* **Sensitive Data Exposure**
  * When an exception occurs, we hide passwords and other sensitive infromation
    from stack traces.
* **Missing Function Level Access Control**
  * Airship has comprehensive yet simple access controls management baked in:
    * Hierarchical group-based and user-based access controls
    * Multi-site architecture where each site has its own permissions matrix
    * Groups can inherit permissions in a hierarchy
    * Permission can be granted to groups or users
    * The UX for all of the above is simple and intuitive
* **Cross-Site Request Forgery**
  * All POST data has mandatory CSRF protection built-in. Additionally,
    Airship offers best-in-class CSRF mitigation techniques that prevents token
    reuse.
* **Using Components with Known Vulnerabilities** - We provide automatic
  security updates.
* **Open Redirects**
  * (Unless you go out of your way to make it happen, of course.)
* **PHP Object Injection**
  * We never use `unserialize()`
* **Insecure Random Number Generator**
  * Airship uses a cryptographically secure random number generator,
    exclusively.
* **Insecure Cryptographic Storage** is a non-issue; we make full use of
  the Sodium cryptographic library (through Paragon Initiative
  Enterprise's Halite API).
  * **Passwords** hashed with `Argon2i` then encrypted with an
    authenticated encryption feature (Xsalsa20 + keyed BLAKE2b)
* **Password-Hashing Denial of Service Attack** and/or **Login Brute-Force**
  * We rate-limit failed login requests based on IP range and username. Each
    successive attempt incurs a progressive delay up to a configurable maximum.
* **Security Misconfiguration**
  * We ship with secure defaults. While you can always weaken security through
    customization, we ship a secure product.

### Other Security Benefits

* Tor-friendly server-side communications
* Manage your security headers from a web interface.
  * Content-Security-Policy
  * HTTP Public-Key-Pinning