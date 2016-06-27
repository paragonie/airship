## Version 1.0.0 - 2016-06-27

  * You can now move or rename directories in our custom page system.
  * Added an AJAX endpoint for clearing the cache remotely.
  * Fixed Javascript race conditions that prevented the rich text editor from
    loading reliably.
  * Cabins, Motifs, and Gadgets can now be disabled (and remain installed).
  * Cabins, Motifs, and Gadgets can now be uninstalled.
  * Added a help/support page that displays system information (for privileged
    users only) and links to the documentation and this Github repository.
  * Administrators can post announcements which show up on the Bridge dashboard
    when users log in. Once a user has read an announcement, they may dismiss
    the message.
  * Bugfix: The Content-Security-Policy management tools didn't allow users to
    allow `data:` URIs because of a Twig template error. Instead of slicing at
    `[-4:]`, we were slicing at `[4:]`.

## Version 0.3.0 - 2016-06-21

  * Implemented a secure account recovery implementation, wherein users can
    opt out of account recovery entirely, or supply a GPG public key. We send a
    random, short-lived token to the email address on file (since Airship
    doesn't store plaintext passwords). If a GPG public key is available, their
    account recovery email will be encrypted by GnuPG.
  * Turned all of the Cabin classes into Gears, so that Gadgets can extend
    their functionality.
  * Gadgets can also override the selected Lens, transparently.
  * Added the option to cache blog posts and blog listings. If cached, comments
    will be loaded from AJAX instead of in the page itself. This should allow a
    single blog post to handle over 10,000 requests per second without a sweat.
  * Updated jQuery to 3.0.0.
  * Regenerate session IDs on login. Thanks [@kelunik](https://github.com/kelunik)
    for bringing this oversight to our attention.
  * Implemented progressive rate-limiting based on two factors: IP subnet and
    username. This covers both the login form and the account recovery form.
  * You can now specify [HPKP headers](https://scotthelme.co.uk/hpkp-http-public-key-pinning)
    on a per-Cabin basis, via the Cabin Management screen.
  * You can now add/remove Cabins, Gadgets, and Motifs from the Bridge.
  * Sysadmins can "lock" installs to prevent an admin account compromise from
    leading to a vulnerable extension from being installed and subsequently
    used by an attacker to compromise the server. Locks come in two varieties:
     * Password-based locks, where you must enter a separate password to
       install a new extension.
     * Absolute locks, which can only be removed by the sysadmin.
  * In Landings, `$this->lens()` will now terminate script execution. If you
    need to fetch the output (e.g. for caching), use `$this->lensRender()`
    instead.
  * Implemented input filters which work on multidimensional arrays (e.g
    `$_POST`). We provide a few examples (one for each cabin's custom config
    and one for the universal config).
  * Implemented optional **Two-Factor Authentication** support via TOTP 
    (e.g. Google Authenticator).
  * Airship now supports in-memory caching via APCu instead of the filesystem.
  * Comments are now loaded with AJAX when you elect to cache a blog post.
  * When you delete a custom directory, you can elect to create redirects
    automatically to guide your passengers to the correct destination.

## Version 0.2.1 - 2016-06-04

  * Bugfix: The Airship Installer failed to assign a default "guest group"
    which caused file downloads to fail when not authenticated.

## Version 0.2.0 - 2016-06-03

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
  * Greatly improved the comment system; now you may reply to other comments.

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
