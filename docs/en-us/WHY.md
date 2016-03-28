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
3. Gears (see #5 below). **For power users.**

All Cabins, Gadgets, and Gears can be assigned to a vendor (which has
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

Each Cabin is its own website. Install as many Cabins as you need.

### 4. Designed by progressive-minded application security professionals.

Airship was started by [Paragon Initiative Enterprises](https://paragonie.com).
We specialize in application security and applied cryptography.

### 5. Our Gear system allows the framework to be extended.

Because of our auto-updater, any local changes made to the Engine files 
will be obliterated whenever an upstream change occurs. To allow users
to extend and customize the core classes to meet their needs, we 
designed our application around the `Gears` system.

Most of the core `Engine` classes can be extended at runtime by the
`Gadgets` you create (or install from the community). Instead of
accessing the core classes directly, load the latest version of the Gear
(which could be our code, or yours).

## The Security Benefits of using Airship

### Vulnerabilities we Prevent
 
* **Using Components with Known Vulnerabilities** - We provide automatic
  security updates.
* **SQL Injection** is effectively mitigated by our use of prepared 
  statements in nearly every context. Where prepared statements aren't
  used, a typecast to int or strict whitelist of allowed characters is
  enforced instead.
* **Cross-Site Scripting** is mitigated on two fronts:
  * **Output Escaping** (rather than *Input* escaping) practively
    prevents most XSS vulnerabilities from even occurring.
  * **Content-Security-Policy headers** act as a second line of defense
    for browsers. This is an exploit mitigation feature which should not
    be *relied* on. It's like a seatbelt for your passengers. 
* **Insecure Cryptographic Storage** is a non-issue; we make full use of
  the Sodium cryptographic library (through Paragon Initiative
  Enterprise's Halite API).
  * **Passwords** hashed with `Argon2i` then encrypted with an
    authenticated encryption feature (Xsalsa20 + keyed BLAKE2b)
