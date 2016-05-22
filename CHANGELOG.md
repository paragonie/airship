## Version 0.2.0 - Not Released Yet

  * Added a WYSIWYG editor.
  * Fix CSS and symlink issues from first squashed commit.
  * Bump minimum Halite version to `2.1`.
  * Implemented Keyggdrasil, an Airship-exclusive protocol that allows
    us to guarantee that all Airships have the same public key and 
    package update history. This is accomplished by a peer verification
    mechanism.
  * Improved Installer workflow.
  * Allow users to select their preferred Motif for each Cabin.
  * Removed validity periods from signing keys. Use revocation instead.
  * Add more security headers out-of-the-box:
    * X-Frame-Options
    * X-XSS-Protection
  * Improved static page caching (now also sends Content-Securiy-Policy
    headers)
  * Added a `HiddenString` class to hide passwords from stack traces.
  * Use Ed25519 signatures to mitigate Hash-DoS from untrusted JSON
    inputs.
  * Added configuration option to cache Twig templates.
  * Users can now delete blog posts.
  * Users can now diff two versions of a blog post.

## Version 0.1.0 - 2016-04-05

Built a CMS with security in mind:

  * Ed25519-signed automatic updating, powered by [Halite](https://github.com/paragonie/halite)
  * Argon2i password hashing
  * Prepared statements to prevent SQLi
  * Context-sensitive escaping (via Twig)
  * Integrated with [CSPBuilder](https://github.com/paragonie/csp-builder),
    plus a web UI to manage the rules
  * CSRF Prevention baked in
  * [Secure long-term authentication](https://paragonie.com/blog/2015/04/secure-authentication-php-with-long-term-persistence#title.2.1)
  * Incredibly powerful and flexible access controls (whitelist-based)
  * Separate authentication (users) from public identities (authors)
