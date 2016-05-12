<?php
declare(strict_types=1);
namespace Airship\LensFunctions;

use \Airship\Engine\{
    Gadgets,
    Gears,
    State
};
use \Airship\Engine\Security\Permissions;
use \Gregwar\RST\Parser as RSTParser;
use \League\CommonMark\CommonMarkConverter;
use \ParagonIE\CSPBuilder\CSPBuilder;
use \ParagonIE\ConstantTime\{
    Base64,
    Base64UrlSafe
};
use \ParagonIE\Halite\Util;

/**
 * Get the base template (normally "base.twig")
 *
 * @return string
 */
function base_template()
{
    $state = State::instance();
    return $state->base_template;
}

/**
 * READ-ONLY access to the state global
 *
 * @param string $name
 * @return array
 */
function cabin_config(string $name): array
{
    $state = State::instance();
    foreach ($state->cabins as $route => $cabin) {
        if ($cabin['name'] === $name) {
            return $cabin;
        }
    }
    return [];
}

/**
 * Get the canon URL for a given Cabin
 *
 * @param string $cabin
 * @return string
 */
function cabin_url(string $cabin = \CABIN_NAME): string
{
    static $lookup = [];
    if (!empty($lookup[$cabin])) {
        // It was cached
        return $lookup[$cabin];
    }
    $state = State::instance();
    foreach ($state->cabins as $c) {
        if ($c['name'] === $cabin) {
            if (isset($c['canon_url'])) {
                $lookup[$cabin] = \rtrim($c['canon_url'], '/').'/';
                return $lookup[$cabin];
            }
            $lookup[$cabin] = '/';
            return $lookup[$cabin];
        }
    }
    return '';
}

/**
 * Used in our cachebust filter
 *
 * @param $relative_path
 * @return string
 */
function cachebust($relative_path)
{
    if ($relative_path[0] !== '/') {
        $relative_path = '/' . $relative_path;
    }
    $absolute = $_SERVER['DOCUMENT_ROOT'] . $relative_path;
    if (\is_readable($absolute)) {
        return $relative_path.'?'.Base64UrlSafe::encode(
            \Sodium\crypto_generichash(
                \file_get_contents($absolute).\filemtime($absolute)
            )
        );
    }
    // Special value
    return $relative_path.'?404NotFound';
}

/**
 * Permission Look-Up
 *
 * @param string $label
 * @param string $context_regex
 * @param string $domain
 * @param int $user_id
 * @return bool
 * @throws \Airship\Alerts\Database\DBException
 */
function can(
    string $label,
    string $context_regex = '',
    string $domain = \CABIN_NAME,
    int $user_id = 0
): bool {
    static $perm = null;
    if ($perm === null) {
        $perm = Gears::get(
            'Permissions',
            \Airship\get_database()
        );
        if (IDE_HACKS) {
            $perm = new Permissions(\Airship\get_database());
        }
    }
    return $perm->can($label, $context_regex, $domain, $user_id);
}

/**
 * @param array ...$args
 * @return array
 */
function cargo(...$args)
{
    return Gadgets::unloadCargo(...$args);
}

/**
 * Content-Security-Policy hash
 *
 * @param string $file
 * @param string $dir
 * @param string $algo
 * @return string
 */
function csp_hash(string $file, string $dir = 'script-src', string $algo = 'sha384'): string
{
    $state = State::instance();
    if (isset($state->CSP)) {
        $checksum = Util::hash('Content Security Policy Hash:' . $file);
        $h1 = substr($checksum, 0, 2);
        $h2 = substr($checksum, 2, 2);
        $fhash = substr($checksum, 4);

        if (\file_exists(ROOT.'/tmp/cache/csp_hash/'.$h1.'/'.$h2.'/'.$fhash.'.txt')) {
            if ($state->CSP instanceof CSPBuilder) {
                $prehash = \file_get_contents(ROOT . '/tmp/cache/csp_hash/' . $h1 . '/' . $h2 . '/' . $fhash . '.txt');
                $state->CSP->preHash(
                    $dir,
                    $prehash,
                    $algo
                );
            }
            return $file;
        }
        // Cache miss.
        if (\preg_match('#^([A-Za-z]+):\/\/#', $file)) {
            $absolute = $file;
        } else {
            if ($file[0] !== '/') {
                $file = '/' . $file;
            }
            $absolute = $_SERVER['DOCUMENT_ROOT'] . $file;
            if (!\file_exists($absolute)) {
                return $file;
            }
        }
        if ($state->CSP instanceof CSPBuilder) {
            $preHash = Base64::encode(
                \hash($algo, \file_get_contents($absolute), true)
            );
            $state->CSP->preHash($dir, $preHash, $algo);
            if (!is_dir(ROOT.'/tmp/cache/csp_hash/'.$h1)) {
                \mkdir(ROOT.'/tmp/cache/csp_hash/'.$h1, 0777);
            }
            if (!is_dir(ROOT.'/tmp/cache/csp_hash/'.$h1.'/'.$h2)) {
                \mkdir(ROOT.'/tmp/cache/csp_hash/'.$h1.'/'.$h2, 0777);
            }

            \file_put_contents(
                ROOT.'/tmp/cache/csp_hash/'.$h1.'/'.$h2.'/'.$fhash.'.txt',
                $preHash
            );
            return $file;
        }
    }
    return $file;
}

/**
 * Content-Security-Policy hash
 *
 * @param string $str
 * @param string $dir
 * @param string $algo
 * @return string
 */
function csp_hash_str(string $str, string $dir = 'script-src', string $algo = 'sha384'): string
{
    $state = State::instance();
    if (isset($state->CSP)) {
        if ($state->CSP instanceof CSPBuilder) {
            $preHash = \hash($algo, $str, true);
            $state->CSP->preHash($dir, Base64::encode($preHash), $algo);
            return $str;
        }
    }
    return $str;
}

/**
 * Generate a nonce, add to the CSP header
 *
 * @param string $dir
 * @return string
 */
function csp_nonce(string $dir = 'script-src'): string
{
    $state = State::instance();
    if (isset($state->CSP)) {
        if ($state->CSP instanceof CSPBuilder) {
            return (string) $state->CSP->nonce($dir);
        }
    }
    return 'noCSPinstalled';
}

/**
 * Insert a CSRF prevention token
 *
 * @param string $lockTo
 * @return mixed
 */
function form_token($lockTo = '')
{
    static $csrf = null;
    if ($csrf === null) {
        $csrf = Gears::get('CSRF');
    }
    return $csrf->insertToken($lockTo);
}

/**
 * Given a URL, only grab the path component (and, optionally, the query)
 *
 * @param string $url
 * @param bool $includeQuery
 * @return string
 */
function get_path_url(string $url, bool $includeQuery = false): string
{
    $path = \parse_url($url, PHP_URL_PATH);
    if ($path) {
        if ($includeQuery) {
            $query = \parse_url($url, PHP_URL_QUERY);
            if ($query) {
                return $url . '?' . $query;
            }
        }
        return $path;
    }
    return '';
}

/**
 * Get supported languages.
 *
 * @todo separate this out
 */
function get_languages(): array
{
    return [
        'en-us' => 'English (U.S.)'
    ];
}

/**
 * Get an author profile's avatar
 *
 * @param int $authorId
 * @param string $which
 * @return string
 */
function get_avatar(int $authorId, string $which): string
{
    return '';
}

/**
 * READ-ONLY access to the state global
 *
 * @param string $key
 * @return array
 */
function global_config(string $key): array
{
    $state = State::instance();
    switch ($key) {
        case 'active_cabin':
            return [
                $state->{$key}
            ];
        case 'base_template':
        case 'cabins':
        case 'cargo':
        case 'motifs':
        case 'gears':
        case 'lang':
        case 'universal':
            return $state->{$key};
        default:
            return [];
    }
}

/**
 * Is this user an administrator?
 *
 * @param int $user_id
 * @return bool
 * @throws \Airship\Alerts\Database\DBException
 */
function is_admin(int $user_id = 0): bool
{
    static $perm = null;
    if ($perm === null) {
        $perm = Gears::get(
            'Permissions',
            \Airship\get_database()
        );
    }
    if ($user_id < 1) {
        $user_id = \Airship\LensFunctions\userid();
    }
    return $perm->isSuperUser($user_id);
}

/**
 * Json_encode and Echo
 *
 * @param mixed $data
 * @param int $indents
 */
function je($data, int $indents = 0)
{
    if ($indents > 0) {
        $left = \str_repeat('    ', $indents);
        echo \implode(
            "\n" . $left,
            \explode(
                "\n",
                \json_encode($data, JSON_PRETTY_PRINT)
            )
        );
        return;
    }
    echo \json_encode($data, JSON_PRETTY_PRINT);
}

/**
 * @return string
 */
function logout_token(): string
{
    $state = State::instance();
    $idx = $state->universal['session_index']['logout_token'];
    if (\array_key_exists($idx, $_SESSION)) {
        return $_SESSION[$idx];
    }
    $_SESSION[$idx] = \Sodium\bin2hex(\random_bytes(16));
    return $_SESSION[$idx];
}


/**
 * Get information about the motifs
 *
 * @return array
 */
function motifs()
{
    $state = State::instance();
    return $state->motifs;
}

/**
 * @param string $string
 * @param bool $return
 * @output HTML
 * @return string
 */
function render_markdown(string $string = '', bool $return = false): string
{
    static $md = null;
    if (empty($md)) {
        $md = new CommonMarkConverter();
    }

    $checksum = Util::hash('Markdown' . $string);

    $h1 = substr($checksum, 0, 2);
    $h2 = substr($checksum, 2, 2);
    $hash = substr($checksum, 4);

    if (\file_exists(ROOT.'/tmp/cache/markdown/'.$h1.'/'.$h2.'/'.$hash.'.txt')) {
        $output = \file_get_contents(ROOT.'/tmp/cache/markdown/'.$h1.'/'.$h2.'/'.$hash.'.txt');
    } else {
        if (!is_dir(ROOT.'/tmp/cache/markdown/'.$h1)) {
            \mkdir(ROOT.'/tmp/cache/markdown/'.$h1, 0777);
        }
        if (!is_dir(ROOT.'/tmp/cache/markdown/'.$h1.'/'.$h2)) {
            \mkdir(ROOT.'/tmp/cache/markdown/'.$h1.'/'.$h2, 0777);
        }
        $output = $md->convertToHtml($string);
        // Cache for later
        \file_put_contents(
            ROOT.'/tmp/cache/markdown/'.$h1.'/'.$h2.'/'.$hash.'.txt',
            $output
        );
        \chmod(
            ROOT.'/tmp/cache/markdown/'.$h1.'/'.$h2.'/'.$hash.'.txt',
            0666
        );
    }
    if ($return) {
        return (string) $output;
    }
    echo $output;
    return '';
}

/**
 * Renders ReStructuredText
 *
 * @param string $string
 * @param bool $return
 * @output HTML
 * @return string
 */
function render_rst(string $string = '', bool $return = false): string
{
    static $rst = null;
    if (empty($rst)) {
        $rst = new RSTParser();
    }

    $checksum = Util::hash('ReStructuredText' . $string);

    $h1 = substr($checksum, 0, 2);
    $h2 = substr($checksum, 2, 2);
    $hash = substr($checksum, 4);

    if (\file_exists(ROOT.'/tmp/cache/rst/'.$h1.'/'.$h2.'/'.$hash.'.txt')) {
        $output = \file_get_contents(ROOT.'/tmp/cache/rst/'.$h1.'/'.$h2.'/'.$hash.'.txt');
    } else {
        if (!is_dir(ROOT.'/tmp/cache/rst')) {
            \mkdir(ROOT.'/tmp/cache/rst', 0777);
        }
        if (!is_dir(ROOT.'/tmp/cache/rst/'.$h1)) {
            \mkdir(ROOT.'/tmp/cache/rst/'.$h1, 0777);
        }
        if (!is_dir(ROOT.'/tmp/cache/rst/'.$h1.'/'.$h2)) {
            \mkdir(ROOT.'/tmp/cache/rst/'.$h1.'/'.$h2, 0777);
        }
        $output = $rst->parse($string);
        // Cache for later
        \file_put_contents(
            ROOT.'/tmp/cache/rst/'.$h1.'/'.$h2.'/'.$hash.'.txt',
            $output
        );
        \chmod(
            ROOT.'/tmp/cache/rst/'.$h1.'/'.$h2.'/'.$hash.'.txt',
            0666
        );
    }
    if ($return) {
        return $output;
    }
    echo $output;
    return '';
}

/**
 * Purify a string using HTMLPurifier
 *
 * @param $string
 * @return string
 */
function purify(string $string = '')
{
    static $state = null;
    if ($state === null) {
        $state = State::instance();
    }
    $checksum = Util::hash('HTML Purifier' . $string);

    $h1 = substr($checksum, 0, 2);
    $h2 = substr($checksum, 2, 2);
    $hash = substr($checksum, 4);

    if (\file_exists(ROOT.'/tmp/cache/html_purifier/'.$h1.'/'.$h2.'/'.$hash.'.txt')) {
        $output = \file_get_contents(ROOT.'/tmp/cache/html_purifier/'.$h1.'/'.$h2.'/'.$hash.'.txt');
    } else {
        if (!is_dir(ROOT.'/tmp/cache/html_purifier')) {
            \mkdir(ROOT.'/tmp/cache/html_purifier', 0777);
        }
        if (!is_dir(ROOT.'/tmp/cache/html_purifier/'.$h1)) {
            \mkdir(ROOT.'/tmp/cache/html_purifier/'.$h1, 0777);
        }
        if (!is_dir(ROOT.'/tmp/cache/html_purifier/'.$h1.'/'.$h2)) {
            \mkdir(ROOT.'/tmp/cache/html_purifier/'.$h1.'/'.$h2, 0777);
        }
        $output = $state->HTMLPurifier->purify($string);
        // Cache for later
        \file_put_contents(
            ROOT.'/tmp/cache/html_purifier/'.$h1.'/'.$h2.'/'.$hash.'.txt',
            $output
        );
        \chmod(
            ROOT.'/tmp/cache/html_purifier/'.$h1.'/'.$h2.'/'.$hash.'.txt',
            0666
        );
    }
    echo $output;
}

/**
 * Markdown then HTMLPurifier
 *
 * @param string $string
 * @return string
 */
function render_purified_markdown(string $string = '')
{
    echo \Airship\LensFunctions\purify(
        \Airship\LensFunctions\render_markdown($string, true)
    );
}

/**
 * ReStructuredText then HTMLPurifier
 *
 * @param string $string
 * @return string
 */
function render_purified_rest(string $string = '')
{
    echo \Airship\LensFunctions\purify(
        \Airship\LensFunctions\render_rst($string, true)
    );
}

/**
 * Get the current user's ID. Returns 0 if not logged in.
 *
 * @return int
 */
function userid(): int
{
    $state = State::instance();
    $idx = $state->universal['session_index']['user_id'];
    return \array_key_exists($idx, $_SESSION)
        ? (int) $_SESSION[$idx]
        : 0;
}

/**
 * Get all of a user's author profiles
 *
 * @param int|null $userId
 * @return array
 * @throws \Airship\Alerts\Database\DBException
 */
function user_authors(int $userId = null): array
{
    if (empty($userId)) {
        $userId = \Airship\LensFunctions\userid();
    }
    $db = \Airship\get_database();
    return $db->run(
        'SELECT * FROM view_hull_users_authors WHERE userid = ?',
        $userId
    );
}

/**
 * Get all of a user's author profiles
 *
 * @param int|null $userId
 * @return array
 * @throws \Airship\Alerts\Database\DBException
 */
function user_author_ids(int $userId = null): array
{
    if (empty($userId)) {
        $userId = \Airship\LensFunctions\userid();
    }
    $db = \Airship\get_database();
    return $db->first(
        'SELECT authorid FROM hull_blog_author_owners WHERE userid = ?',
        $userId
    );
}

/**
 * Get the user's public display name.
 *
 * @param int|null $userId
 * @return string
 * @throws \Airship\Alerts\Database\DBException
 */
function user_display_name(int $userId = null): string
{
    if (empty($userId)) {
        $userId = \Airship\LensFunctions\userid();
    }
    $db = \Airship\get_database();
    return $db->cell(
        'SELECT COALESCE(display_name, username) FROM airship_users WHERE userid = ?',
        $userId
    );
}

/**
 * Get the user's selected Motif
 *
 * @param int|null $userId
 * @param string $cabin
 * @return array
 */
function user_motif(int $userId = null, string $cabin = \CABIN_NAME): array
{
    static $userCache = [];
    $state = State::instance();
    if (empty($userId)) {
        $userId = \Airship\LensFunctions\userid();
        if (empty($userId)) {
            $k = \array_keys($state->motifs)[0];
            return $state->motifs[$k];
        }
    }
    if (isset($userCache[$userId])) {
        return $state->motifs[$userCache[$userId]];
    }
    $db = \Airship\get_database();
    $userPrefs = $db->cell(
        'SELECT preferences FROM airship_user_preferences WHERE userid = ?',
        $userId
    );
    if (!empty($userPrefs)) {
        $userPrefs = \Airship\parseJSON($userPrefs, true);
        if (isset($userPrefs['motif'][$cabin])) {
            $split = \explode('/', $userPrefs['motif'][$cabin]);
            foreach ($state->motifs as $k => $motif) {
                if ($motif['config']['supplier'] === $split[0]) {
                    if ($motif['config']['name'] === $split[1]) {
                        $userCache[$userId] = $k;
                        return $state->motifs[$k];
                    }
                }
            }
        }
    }
    $k = \array_keys($state->motifs)[0];
    $userCache[$userId] = $k;
    return $state->motifs[$k];
}

/**
 * Get a user's username, given their user ID
 *
 * @param int|null $userId
 * @return string
 * @throws \Airship\Alerts\Database\DBException
 */
function user_name(int $userId = null): string
{
    if (empty($userId)) {
        $userId = \Airship\LensFunctions\userid();
    }
    $db = \Airship\get_database();
    return $db->cell(
        'SELECT username FROM airship_users WHERE userid = ?',
        $userId
    );
}
