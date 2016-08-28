## Version 1.3.0 (Not released yet)

  * Significant UI/UX improvements.
    * Redesigned the Bridge UI to be more suitable for a control panel.
    * The left menu in the Bridge is now collapsable, but automatically
      opens the sections which indicate your current location in the 
      cabin.
  * Update [Halite](https://github.com/paragonie/halite) to 2.2.0.
  * Added a `WhiteList` filter, which is a strict typed alternative to
    switch-case whitelisting.
  * [#129](https://github.com/paragonie/airship/issues/129):
    Extension developers can now make their motifs configurable by
    end users.

## Version 1.2.8 - 2016-07-26

  * In addition to expiring after a set period of time, account recovery
    URLs can only be used once. This fixes this feature by making it in
    line with the expected behavior.
  * Bootstrap (JS/CSS framework) was removed, as we don't use it.
  * Dependency update (e.g. HTMLPurifier 4.8.0).

## Version 1.2.7 - 2016-07-18

  * Added logic to the Airship updater to attempt to run `composer install`
    (if we can) if an update includes a `composer.lock` file.

## Version 1.2.6 - 2016-07-18

  * Update Guzzle [for upstream fix to CVE-2016-5385](https://github.com/guzzle/guzzle/commit/9d521b23146cb6cedd772770a2617fd6cbdb1596).

## Version 1.2.5 - 2016-07-17

  * Separate MIME type output filter into its own method, so it can be covered
    by unit tests.
  * [#115](https://github.com/paragonie/airship/issues/115):
    More readable extension log.
  * Disable MIME sniffing everywhere.

## Version 1.2.4 - 2016-07-13

  * Add several headers to static file downloads to prevent stored XSS
    vulnerabilities in Internet Explorer and Flash. [HackerOne Report](https://hackerone.com/reports/151231).

## Version 1.2.3 - 2016-07-13

  * The tool created in `1.2.2` for automated deployments was not
    functional before Airship was first installed.

## Version 1.2.2 - 2016-07-13

  * Improved Continuum/Keyggdrasil logging.
  * Created a tool for automating step one of the installer from the command 
    line.

## Version 1.2.1 - 2016-07-09

  * [#111](https://github.com/paragonie/airship/issues/111):
    Fixed bugs with the notary API that was breaking the auto-updater.
  * Corrected the variable names used to handle uncaught exceptions.

## Version 1.2.0 - 2016-07-07

  * [#46](https://github.com/paragonie/airship/issues/46):
    You can now specify meta tags for blog posts.
  * [#57](https://github.com/paragonie/airship/issues/57):
    Our `Database` class can now connect to UNIX sockets. Set the hostname to
    `unix:/path/to/socket`.
  * [#65](https://github.com/paragonie/airship/issues/67):
    You can now add database connections to the local database connection pool.
    You can also add database connection pools for app-specific purposes.
    In the future, we'll support more than just PostgreSQL.
  * Database connections are now lazy instead of eager, which should result in
    less overhead on HTTP requests that don't use additional databases. (In our
    cabins, this affects nothing.)
  * [#67](https://github.com/paragonie/airship/issues/67):
    Blog posts are now linked from the Bridge.
  * [#69](https://github.com/paragonie/airship/issues/69):
    Added a dedicated log for the auto-updater.
  * [#70](https://github.com/paragonie/airship/issues/70):
    Added a faster install option for deploying an Airship in a hurry, with the
    sane defaults we provide.
  * [#77](https://github.com/paragonie/airship/issues/77):
    Fixed responsive UI/UX warts (i.e. small links and buttons). 
  * [#80](https://github.com/paragonie/airship/issues/80):
    If the GD extension isn't loaded, render QR codes for two-factor
    authentication as SVG instead. 
  * [#88](https://github.com/paragonie/airship/issues/88):
    The installer now uses Zxcvbn to enforce a minimum password strength for
    administrator accounts.
  * Added more input filters for one-dimensional arrays consisting of a static
    type (e.g. `int[]` will return a one-dimensional array of integers).
  * Unhandled exceptions now display a static page outside of debug mode, which
    is a little better for UX than a white page.

## Version 1.1.4 - 2016-07-02

  * i18n - run parameters through HTMLPurifier (with caching) to prevent future
    XSS payloads in case someone forgets to escape these parameters. HTML is
    still allowed, so if you're inserting in an HTML attribute, use the 
    `|e('html_attr')` filter on your input.
  * Use the correct POST index in account recovery.
  * Treat SVG and XML files as plaintext, to prevent stored XSS. Reported on
    [HackerOne](https://hackerone.com/reports/148853).
  * Send `Content-Security-Policy` headers on file downloads as well as web 
    pages. Just in case another file type exists in the world that executes
    JavaScript when the file is viewed.

## Version 1.1.3 - 2016-07-01

  * Fixed `E_NOTICE`s with the auto-updater.
  * Identified a bug in the backend server that wasn't publishing commit hashes
    in CMS Airship core updates. Going forward, the commit hash should be
    included in each release.
  * Only allow HTTP and HTTPS URLs in blog comments.
  * Proactively mitigate stored XSS in other invocations of `__()`.

## Version 1.1.2 - 2016-07-01

  * Fixed a stored XSS vulnerability introduced by using `sprintf()`, which
    bypassed Twig's autoescape. [HackerOne report](https://hackerone.com/reports/148741).

## Version 1.1.1 - 2016-07-01

  Fixes for bugs reported by [@kelunik](https://github.com/kelunik) and
  [@co60ca](https://github.com/co60ca).
  
  * [#61](https://github.com/paragonie/airship/issues/61):
    Comments need a min-height attribute.
  * [#62](https://github.com/paragonie/airship/issues/62), [#64](https://github.com/paragonie/airship/issues/64):
    The default configuration is wrong.
  * [#66](https://github.com/paragonie/airship/issues/66):
    The default configuration broke the 2FA page.

## Version 1.1.0 - 2016-07-01

  * [#41](https://github.com/paragonie/airship/issues/41):
    Don't raise an `E_NOTICE` upon receiving an invalid CSRF token.
  * [#42](https://github.com/paragonie/airship/issues/42):
    We now have a Dockerfile for easy deployment. Thanks [@kelunik](https://github.com/kelunik)
    and [@co60ca](https://github.com/co60ca).
  * [#47](https://github.com/paragonie/airship/issues/47):
    If you make a typo when filling in the database credentials on first run,
    it will no longer proceed silently then fail catastrophically in the last
    step.
  * [#50](https://github.com/paragonie/airship/issues/50):
    Display the correct version in the Installer.
  * [#56](https://github.com/paragonie/airship/issues/56):
    If libsodium is not set up correctly, show an error page explaining the
    problem and guiding the user towards the solution. Thanks [@co60ca](https://github.com/co60ca).
  * Various user interface improvements based on feedback from the initial
    launch.
  * You can now pass an input filter to `$this->post()` from a landing and it
    will be enforced upon the POST data. If a type error occurs, it simply
    returns `false`.
  * Fixed a bug that prevented CAPTCHAs from loading on static blog posts.
    Thanks [@kyhwana](https://github.com/kyhwana) for reporting this.
  * The "parent category" select box now renders properly.
  * The authors' photos menu is properly prepopulated by the contexts we use
    in Airship. Extensions are free to supply their own contexts.

## Version 1.0.2 - 2016-06-28

  * Fixed a default configuration issue which caused Cabins to be disabled.
  * Improved the UX of the Installer. Populate more default settings.

## Version 1.0.1 - 2016-06-27

  * Fixed a syntax error that snuck into our installer SQL code.

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
