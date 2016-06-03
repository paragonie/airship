## Version 0.2.0 - Not Released Yet

  * Added a WYSIWYG editor (dubbed "Rich Text" to users).
  * Fix CSS and symlink issues from first squashed commit.
  * Fixed router bugs. Now `bridge.example.com` and `example.com/bridge` are
    both acceptable ways to access the bridge (this decision is left to user
    configuration, of course).
  * Bump minimum Halite version to `2.1`.
  * Implemented [Keyggdrasil](https://paragonie.com/blog/2016/05/keyggdrasil-continuum-cryptography-powering-cms-airship),
    an Airship-exclusive protocol that allows us to guarantee that all Airships
    have the same public key and package update history. This is accomplished
    by a peer verification mechanism.
  * Improved Airship Installer workflow.
  * Added command line scripts to install new Cabins, Gadgets, and Motifs.
  * Allow users to select their preferred Motif for each Cabin.
  * Removed validity periods from signing keys. We'll use revocation instead.
  * Add more security headers out-of-the-box:
    * X-Frame-Options
    * X-XSS-Protection
  * Improved static page caching (now also sends Content-Securiy-Policy
    headers).
  * Added a `HiddenString` class to hide passwords from stack traces.
  * Use Ed25519 signatures to mitigate Hash-DoS from untrusted JSON
    inputs.
  * Added configuration option to cache Twig templates.
  * Users can now delete blog posts.
  * Users can now diff two versions of a blog post.
  * Users can now add/remove other users to the same Author.
  * Users can now selected uploaded image files to use for biography images and
    avatars to accompany their blog comments.
  * Lots of reorganization, refactoring, and clean-up.
  * Moved the [CMS Airship Documentation](https://github.com/paragonie/airship-docs)
    to its own dedicated git repository.
  * When you change a blog post's slug, you can optionally create an HTTP 301
    redirect to the new URL to prevent visitors from getting an unfortunate
    HTTP 404 error. This allows you to funnel traffic towards a meaningful
    destination.
  * Implemented the redirect management section. Now you can edit/delete custom
    URL redirects (some of which are created when you delete/rename content).

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
