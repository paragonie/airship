<?php
declare(strict_types=1);
namespace Airship\LensFunctions;

use Airship\Engine\{
    Gadgets,
    Gears,
    Security\Util,
    State
};
use Airship\Engine\Security\Permissions;
use Gregwar\RST\Parser as RSTParser;
use League\CommonMark\CommonMarkConverter;
use ParagonIE\CSPBuilder\CSPBuilder;
use ParagonIE\ConstantTime\{
    Base64,
    Base64UrlSafe,
    Binary
};
use ParagonIE\Halite\{
    Asymmetric\SignaturePublicKey,
    Asymmetric\SignatureSecretKey,
    File,
    Symmetric\AuthenticationKey,
    Util as CryptoUtil
};

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
function cabin_config(string $name = \CABIN_NAME): array
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
 * READ-ONLY access to the cabin settings
 *
 * @param string $name
 * @return array
 */
function cabin_custom_config(string $name = \CABIN_NAME): array
{
    return \Airship\loadJSON(
        ROOT . '/Cabin/' . $name . '/config/config.json'
    );
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
                $lookup[$cabin] = \rtrim($c['canon_url'], '/') . '/';
                return $lookup[$cabin];
            }
            $lookup[$cabin] = '/';
            return $lookup[$cabin];
        }
    }
    return '';
}

/**
 * Used in our cachebust filter. This is mostly useful for HTML5 app caching
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
        // Halite's File::checksum() uses less memory than reading the entire
        // file into memory.
        $key = new AuthenticationKey(
            CryptoUtil::raw_hash(
                (string) \filemtime($absolute)
            )
        );
        return $relative_path . '?' . Base64UrlSafe::encode(
            File::checksum(
                $absolute,
                $key,
                true
            )
        );
    }
    // Special value
    return $relative_path . '?404NotFound';
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
    }
    if (!($perm instanceof Permissions)) {
        return false;
    }
    return $perm->can($label, $context_regex, $domain, $user_id);
}

/**
 * Wrapper for `Gadgets::unloadCargo()`
 *
 * @param string $name
 * @param int $offset
 * @return array
 */
function cargo(string $name, int $offset = 0): array
{
    return Gadgets::unloadCargo($name, $offset);
}

/**
 * Hash a file and store its hash in the Content-Security-Policy header
 *
 * @param string $file
 * @param string $dir
 * @param string $algo
 * @return string
 */
function csp_hash(
    string $file,
    string $dir = 'script-src',
    string $algo = 'sha384'
): string {
    $state = State::instance();
    if (isset($state->CSP)) {
        $checksum = CryptoUtil::hash(
            'Content Security Policy Hash:' . $file
        );
        $h1 = Binary::safeSubstr($checksum, 0, 2);
        $h2 = Binary::safeSubstr($checksum, 2, 2);
        $fhash = Binary::safeSubstr($checksum, 4);

        $fileName = \implode(
            '/',
            [
                ROOT,
                'tmp',
                'cache',
                'csp_hash',
                $h1,
                $h2,
                $fhash . '.txt'
            ]
        );
        if (\file_exists($fileName)) {
            if ($state->CSP instanceof CSPBuilder) {
                $prehash = \file_get_contents($fileName);
                if (!\is_string($prehash)) {
                    // Network connection errors
                    return $file;
                }
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
            $contents = \file_get_contents($absolute);
            if (!\is_string($contents)) {
                // Network connection errors
                return $file;
            }
            $preHash = Base64::encode(
                \hash($algo, \file_get_contents($absolute), true)
            );
            $state->CSP->preHash($dir, $preHash, $algo);

            $dirName = \implode(
                '/',
                [
                    ROOT,
                    'tmp',
                    'cache',
                    'csp_hash',
                    $h1,
                    $h2
                ]
            );
            if (!\is_dir($dirName)) {
                \mkdir($dirName, 0775, true);
            }

            \file_put_contents(
                $dirName . '/' . $fhash . '.txt',
                $preHash
            );
            return $file;
        }
    }
    return $file;
}

/**
 * Hash a string and store its hash in the Content-Security-Policy header
 *
 * @param string $str   The data we are hashing
 * @param string $dir   The CSP Directive
 * @param string $algo  Which hash algorithm?
 * @return string       $str
 */
function csp_hash_str(
    string $str,
    string $dir = 'script-src',
    string $algo = 'sha384'
): string {
    $state = State::instance();
    if (isset($state->CSP)) {
        if ($state->CSP instanceof CSPBuilder) {
            $preHash = \hash($algo, $str, true);
            $state->CSP->preHash(
                $dir,
                Base64::encode($preHash),
                $algo
            );
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
    return 'noCSPInstalled';
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
 * Display the notary <meta> tag.
 *
 * @param SignaturePublicKey $pk
 */
function display_notary_tag(SignaturePublicKey $pk = null)
{
    $state = State::instance();
    $notary = $state->universal['notary'];
    if (!empty($notary['enabled'])) {
        if (!$pk) {
            $sk = $state->keyring['notary.online_signing_key'];
            if (!($sk instanceof SignatureSecretKey)) {
                return;
            }
            $pk = $sk
                ->derivePublicKey()
                ->getRawKeyMaterial();
        }
        echo '<meta name="airship-notary" content="' . 
                Base64UrlSafe::encode($pk) .
            '; channel=' . Util::noHTML($notary['channel']) .
            '; url=' . cabin_url('Bridge') . 'notary' .
        '" />';
    }
}

/**
 * Get supported languages. Eventually there will be more than one.
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
    static $cache = [];
    static $db = null;
    if (!$db) {
        $db = \Airship\get_database();
    }
    // If someone comments 100 times, we only want to look up their avatar once.
    $key = CryptoUtil::hash(
        \http_build_query([
            'author' => $authorId,
            'which' => $which
        ])
    );
    if (!isset($cache[$key])) {
        $file = $db->row(
            "SELECT
                 f.*,
                 a.slug
             FROM
                 hull_blog_author_photos p
             JOIN
                 hull_blog_authors a
                 ON p.author = a.authorid
             JOIN
                 hull_blog_photo_contexts c
                 ON p.context = c.contextid
             JOIN
                 airship_files f
                 ON p.file = f.fileid
             WHERE
                 c.label = ? AND a.authorid = ?
             ",
            $which,
            $authorId
        );
        if (empty($file)) {
            $cache[$key] = '';
        } else {
            if (empty($file['directory'])) {
                $cabin = $file['cabin'];
            }  else {
                $dirId = $file['directory'];
                do {
                    $dir = $db->row(
                        "SELECT parent, cabin FROM airship_dirs WHERE directoryid = ?",
                        $dirId
                    );
                    $dirId = $dir['parent'];
                } while (!empty($dirId));
                $cabin = $dir['cabin'];
            }
            $cache[$key] = \Airship\LensFunctions\cabin_url($cabin) .
                'files/author/' .
                $file['slug'] .
                '/photos/' .
                $file['filename'];
        }
    }

    return $cache[$key];
}

/**
 * Purify a string using HTMLPurifier
 *
 * @param $string
 * @return string
 */
function get_purified(string $string = '')
{
    static $state = null;
    if ($state === null) {
        $state = State::instance();
    }
    $checksum = CryptoUtil::hash(
        'HTML Purifier' . $string
    );

    $h1 = Binary::safeSubstr($checksum, 0, 2);
    $h2 = Binary::safeSubstr($checksum, 2, 2);
    $hash = Binary::safeSubstr($checksum, 4);

    $cacheDir = \implode(
        '/',
        [
            ROOT,
            'tmp',
            'cache',
            'html_purifier',
            $h1,
            $h2
        ]
    );

    if (\file_exists($cacheDir . '/' . $hash . '.txt')) {
        $output = \file_get_contents(
            $cacheDir . '/' . $hash . '.txt'
        );
    } else {
        if (!\is_dir($cacheDir)) {
            \mkdir($cacheDir, 0775, true);
        }
        $output = $state->HTMLPurifier->purify($string);
        // Cache for later
        \file_put_contents(
            $cacheDir . '/' . $hash . '.txt',
            $output
        );
        \chmod(
            $cacheDir . '/' . $hash . '.txt',
            0664
        );
    }
    return $output;
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
 * @param int $userID
 * @return bool
 * @throws \Airship\Alerts\Database\DBException
 */
function is_admin(int $userID = 0): bool
{
    static $perm = null;
    if ($perm === null) {
        $perm = Gears::get(
            'Permissions',
            \Airship\get_database()
        );
        if (!($perm instanceof Permissions)) {
            return false;
        }
    }
    if ($userID < 1) {
        $userID = \Airship\LensFunctions\userid();
    }
    return $perm->isSuperUser($userID);
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
 * Return the user's logout token. This is to prevent logout via CSRF.
 *
 * @return string
 */
function logout_token(): string
{
    if (\array_key_exists('logout_token', $_SESSION)) {
        return $_SESSION['logout_token'];
    }
    $_SESSION['logout_token'] = \Sodium\bin2hex(
        \random_bytes(16)
    );
    return $_SESSION['logout_token'];
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
 * Unload the "next" cargo, using an internal iterator.
 *
 * @param string $cargoName
 * @return array
 */
function next_cargo(string $cargoName)
{
    return Gadgets::unloadNextCargo($cargoName);
}

/**
 * Render user input, with CommonMark.
 *
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

    $checksum = CryptoUtil::hash('Markdown' . $string);

    $h1 = Binary::safeSubstr($checksum, 0, 2);
    $h2 = Binary::safeSubstr($checksum, 2, 2);
    $hash = Binary::safeSubstr($checksum, 4);

    $cacheDir = \implode(
        '/',
        [
            ROOT,
            'tmp',
            'cache',
            'markdown',
            $h1,
            $h2
        ]
    );

    if (\file_exists($cacheDir . '/' . $hash . '.txt')) {
        $output = \file_get_contents(
            $cacheDir . '/' . $hash . '.txt'
        );
    } else {
        if (!\is_dir($cacheDir)) {
            \mkdir($cacheDir, 0775, true);
        }
        $output = $md->convertToHtml($string);
        // Cache for later
        \file_put_contents(
            $cacheDir . '/' . $hash . '.txt',
            $output
        );
        \chmod(
            $cacheDir . '/' . $hash . '.txt',
            0664
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
        $rst = (new RSTParser())
            ->setIncludePolicy(false);
    }

    $checksum = CryptoUtil::hash('ReStructuredText' . $string);

    $h1 = Binary::safeSubstr($checksum, 0, 2);
    $h2 = Binary::safeSubstr($checksum, 2, 2);
    $hash = Binary::safeSubstr($checksum, 4);

    $cacheDir = \implode(
        '/',
        [
            ROOT,
            'tmp',
            'cache',
            'rst',
            $h1,
            $h2
        ]
    );

    if (\file_exists($cacheDir . '/' . $hash . '.txt')) {
        $output = \file_get_contents(
            $cacheDir . '/' . $hash . '.txt'
        );
    } else {
        if (!\is_dir($cacheDir)) {
            \mkdir($cacheDir, 0775, true);
        }
        $output = (string) $rst->parse($string);

        // Cache for later
        \file_put_contents(
            $cacheDir . '/' . $hash . '.txt',
            $output
        );
        \chmod(
            $cacheDir . '/' . $hash . '.txt',
            0664
        );
    }
    if ($return) {
        return $output;
    }
    echo $output;
    return '';
}

/**
 * Purify a string using HTMLPurifier. Echo its contents.
 *
 * @param $string
 * @return void
 */
function purify(string $string = '')
{
    echo get_purified($string);
}

/**
 * Markdown then HTMLPurifier
 *
 * @param string $string
 * @param bool $return
 * @return string|null
 */
function render_purified_markdown(string $string = '', bool $return = false)
{
    if ($return) {
        \ob_start();
    }
    \Airship\LensFunctions\purify(
        \Airship\LensFunctions\render_markdown($string, true)
    );
    if ($return) {
        return \ob_get_clean();
    }
    return null;
}

/**
 * ReStructuredText then HTMLPurifier
 *
 * @param string $string
 * @param bool $return
 * @return string|null
 */
function render_purified_rest(string $string = '', bool $return = false)
{
    if ($return) {
        \ob_start();
    }
    \Airship\LensFunctions\purify(
        \Airship\LensFunctions\render_rst($string, true)
    );
    if ($return) {
        return \ob_get_clean();
    }
    return null;
}

/**
 * Get the current user's ID. Returns 0 if not logged in.
 *
 * @return int
 */
function userid(): int
{
    return \array_key_exists('userid', $_SESSION)
        ? (int) $_SESSION['userid']
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
    $authors = $db->run(
        'SELECT * FROM view_hull_users_authors WHERE userid = ?',
        $userId
    );
    if (empty($authors)) {
        return [];
    }
    return $authors;
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
    $authors = $db->first(
        'SELECT authorid FROM hull_blog_author_owners WHERE userid = ?',
        $userId
    );
    if (empty($authors)) {
        return [];
    }
    return $authors;
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
    $displayName = $db->cell(
        "SELECT
             COALESCE(
                 display_name,
                 real_name,
                 username
             )
         FROM
             airship_users
         WHERE
             userid = ?
         ",
        $userId
    );
    if (empty($displayName)) {
        return '';
    }
    return $displayName;
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

    if (\count($state->motifs) === 0) {
        return [];
    }
    if (empty($userId)) {
        $userId = \Airship\LensFunctions\userid();
        if (empty($userId)) {
            $k = \array_keys($state->motifs)[0];
            return $state->motifs[$k] ?? [];
        }
    }
    // Did we cache these preferences?
    if (isset($userCache[$userId])) {
        return $state->motifs[$userCache[$userId]];
    }

    $db = \Airship\get_database();

    $userPrefs = $db->cell(
        'SELECT preferences FROM airship_user_preferences WHERE userid = ?',
        $userId
    );

    if (empty($userPrefs)) {
        // Default
        $k = \array_keys($state->motifs)[0];
        $userCache[$userId] = $k;
        return $state->motifs[$k] ?? [];
    }

    $userPrefs = \Airship\parseJSON($userPrefs, true);
    if (isset($userPrefs['motif'][$cabin])) {
        $split = \explode('/', $userPrefs['motif'][$cabin]);
        foreach ($state->motifs as $k => $motif) {
            if (empty($motif['config'])) {
                continue;
            }
            if (
                $motif['supplier'] === $split[0]
                    &&
                $motif['name'] === $split[1]
            ) {
                // We've found a match:
                $userCache[$userId] = $k;
                return $state->motifs[$k];
            }
        }
    }

    // When all else fails, go with the first one
    $k = \array_keys($state->motifs)[0];
    $userCache[$userId] = $k;
    return $state->motifs[$k] ?? [];
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

/**
 * Get the user's public display name.
 *
 * @param int|null $userId
 * @return string
 * @throws \Airship\Alerts\Database\DBException
 */
function user_unique_id(int $userId = null): string
{
    if (empty($userId)) {
        $userId = \Airship\LensFunctions\userid();
    }
    $db = \Airship\get_database();
    return $db->cell(
        'SELECT uniqueid FROM airship_users WHERE userid = ?',
        $userId
    );
}
