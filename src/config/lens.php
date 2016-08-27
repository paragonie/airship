<?php
declare(strict_types=1);

use Airship\Engine\Lens;

/**
 * GLOBAL LENS MODIFICATIONS GO HERE
 * 
 * We can add filters, functions, etc. to the current lens if we want
 *
 * @global Lens $lens
 */

// Expose PHP's built-in functions as a filter
$lens->filter('abs', 'abs');
$lens->filter('addslashes', 'addslashes');
$lens->filter('preg_quote', 'preg_quote');
$lens->filter('ceil', 'ceil');
$lens->filter('floor', 'floor');
$lens->filter('ucfirst', 'ucfirst');
/**
 * @filter cachebust
 * Cache-busting filter
 * 
 * Usage: {{ "/path/to/file"|cachebust }}
 */
$lens->filter('cachebust', '\\Airship\\LensFunctions\\cachebust');
/**
 * @filter gravatar
 * Get a gravtar URL
 *
 * Usage: {{ "user@example.com"|gravatar }}
 */
$lens->filter('gravatar', '\\Airship\\get_gravatar_url');


/**
 * @filter CleanMarkdown
 * Caching, Markdown parser wrapper + HTMLPurifier
 *
 * Usage: {{ someString|CleanMarkdown }}
 */
$lens->filter('CleanMarkdown', '\\Airship\\LensFunctions\\render_purified_markdown');
/**
 * @filter Markdown
 * Caching, Markdown parser wrapper
 * 
 * Usage: {{ someString|Markdown }}
 */
$lens->filter('Markdown', '\\Airship\\LensFunctions\\render_markdown');


/**
 * @filter CleanRST
 * Caching, ReStructuredText parser wrapper
 *
 * Usage: {{ someString|CleanRST }}
 */
$lens->filter('CleanRST', '\\Airship\\LensFunctions\\render_purified_rest');

/**
 * @filter RST
 * Caching, ReStructuredText parser wrapper
 * 
 * Usage: {{ someString|RST }}
 */
$lens->filter('RST', '\\Airship\\LensFunctions\\render_rest');

/**
 * @filter purify
 * Caching, HTMLPurifier wrapper
 * 
 * Usage: {{ someString|Markdown }}
 */
$lens->filter('purify', '\\Airship\\LensFunctions\\purify');

# ~ # ~ # ~ # ~ # ~ # ~ # ~ # ~ # ~ # ~ # ~ # ~ # ~ # ~ # ~ # ~ # ~ # ~ # ~ # ~ 

if (!isset($_GET)) {
    $_GET = [];
}
if (!isset($_POST)) {
    $_POST = [];
}
if (!isset($_SESSION)) {
    $_SESSION = [];
}
$lens->addGlobal('_GET', $_GET);
$lens->addGlobal('_POST', $_POST);
$lens->addGlobal('_SESSION', $_SESSION);
$lens->addGlobal('_COOKIE', $_COOKIE);
$lens->addGlobal('_REQUEST_URI', $_SERVER['REQUEST_URI'] ?? '/');

$lens->func('__', '\\__');
$lens->func('cargo');
$lens->func('base_template');
$lens->func('form_token');
$lens->func('motifs');
$lens->func('cabin_config');
$lens->func('cabin_custom_config');
$lens->func('cabin_url');
$lens->func('can');
$lens->func('csp_hash');
$lens->func('csp_hash_str');
$lens->func('csp_nonce');
$lens->func('display_notary_tag');
$lens->func('get_avatar');
$lens->func('get_languages');
$lens->func('get_path_url');
$lens->func('global_config');
$lens->func('is_admin');
$lens->func('logout_token');
$lens->func('next_cargo');
$lens->func('userid');
$lens->func('user_authors');
$lens->func('user_author_ids');
$lens->func('user_display_name');
$lens->func('user_motif');
$lens->func('user_name');
$lens->func('user_unique_id');

$lens_edited = true;

/**
 * Permissions functions -- looks at the default database
 */

$lens->func(
    'can_create',
    function(...$args) {
        return \Airship\LensFunctions\can('create', ...$args);
    }
);
$lens->func(
    'can_read',
    function(...$args) {
        return \Airship\LensFunctions\can('read', ...$args);
    }
);

$lens->func(
    'can_update',
    function(...$args) {
        return \Airship\LensFunctions\can('update', ...$args);
    }
);
$lens->func(
    'can_delete',
    function(...$args) {
        return \Airship\LensFunctions\can('delete', ...$args);
    }
);