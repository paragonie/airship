<?php
declare(strict_types=1);
namespace Airship;

use \Airship\Alerts\FileSystem\{
    AccessDenied,
    FileNotFound
};
use \Airship\Alerts\Database\{
    DBException,
    NotImplementedException
};
use \Airship\Engine\{
    Database,
    State
};
use \ParagonIE\ConstantTime\Base64UrlSafe;
use \ReCaptcha\ReCaptcha;
use ReCaptcha\RequestMethod\CurlPost;

\define('AIRSHIP_VERSION', '0.1.0');

/**
 * Do all of these keys exist in the target array
 *
 * @param array $keys
 * @param array $haystack
 *
 * @return bool
 */
function all_keys_exist(array $keys = [], array $haystack = []): bool
{
    $allFound = !empty($haystack) || empty($keys);
    foreach ($keys as $key) {
        $allFound = $allFound && \array_key_exists($key, $haystack);
    }
    return $allFound;
}

/**
 * Inverse of PHP's http_build_query()
 *
 * @param string $queryString
 * @return array
 */
function array_from_http_query(string $queryString = ''): array
{
    $arr = [];
    \parse_str($queryString, $arr);
    return $arr ?? [];
}

/**
 * @param array $new
 * @param array $old
 * @return array
 */
function array_multi_diff(array $new, array $old): array
{
    $ret = [];
    $new_keys = \array_diff(
        \array_keys($new),
        \array_keys($old)
    );
    foreach (\array_keys($old) as $k) {
        if (!isset($new['' . $k])) {
            // This is part of the diff
            $ret ['' . $k] = $old[$k];
        }
    }
    foreach ($new_keys as $k) {
        $ret ['' . $k] = $new[$k];
    }
    $diffKeys = \array_diff(
        \array_keys($old),
        \array_keys($new)
    );
    foreach ($diffKeys as $k) {
        unset($ret[$k]);
    }
    $commonKeys = \array_intersect(
        \array_keys($new),
        \array_keys($old)
    );
    foreach ($commonKeys as $key) {
        $ret['' . $key] = \array_diff_assoc($new[$key], $old[$key]);
    }
    return $ret;
}

/**
 * Register a PSR-4 autoloader for a given namespace and directory
 * 
 * @param string $namespace
 * @param string $directory
 * @return boolean
 */
function autoload(string $namespace, string $directory): bool
{
    $ds = DIRECTORY_SEPARATOR;
    $ns = trim($namespace, '\\'.$ds);
    $dir = preg_replace('#^~'.$ds.'#', ROOT.$ds, $directory);
   
    return \spl_autoload_register(
        function(string $class) use ($ds, $ns, $dir)
        {
            // project-specific namespace prefix
            $prefix = $ns.'\\';

            // base directory for the namespace prefix
            $base_dir =  $dir.$ds;

            // does the class use the namespace prefix?
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                // no, move to the next registered autoloader
                return;
            }

            // get the relative class name
            $relative_class = substr($class, $len);

            // replace the namespace prefix with the base directory, replace
            // namespace separators with directory separators in the relative 
            // class name, append with .php
            $file = $base_dir . str_replace('\\', $ds, $relative_class) . '.php';
            
            // if the file exists, require it
            if (file_exists($file)) {
                require $file;
            }
        }
    );
}

/**
 * A wrapper for explode($a, trim($b, $a))
 *
 * @param $str
 * @param $token
 * @return array
 */
function chunk(string $str, string $token = '/'): array
{
    return \explode($token, \trim($str, $token));
}

/**
 * Create a configuration writer
 *
 * @param string $rootDir
 * @return \Twig_Environment
 */
function configWriter(string $rootDir)
{
    $twigLoader = new \Twig_Loader_Filesystem($rootDir);
    $twigEnv = new \Twig_Environment($twigLoader);
    $twigEnv->addFilter(
        new \Twig_SimpleFilter('je', function ($data, int $indents = 0) {
            return \Airship\LensFunctions\je($data, $indents);
        })
    );
    return $twigEnv;
}

/**
 * Merge several CSP policies
 *
 * @param \array[] ...$policies
 * @return array
 */
function csp_merge(array ...$policies): array
{
    $return = [];
    $n = \count($policies);
    for ($i = 0; $i < $n; ++$i) {
        foreach ($policies[$i] as $k => $data) {
            if (isset($return[$k])) {
                if ($k === 'upgrade-insecure-requests') {
                    $return[$k] = $return[$k] || $data;
                    continue;
                } elseif ($k === 'inherit') {
                    continue;
                }
                $return[$k]['allow'] = \array_merge(
                    $return[$k]['allow'] ?? [],
                    $data['allow'] ?? []
                );
                $return[$k]['data'] =
                    ($return[$k]['data'] ?? false) || !empty($data['data']);
                $return[$k]['self'] =
                    ($return[$k]['self'] ?? false) || !empty($data['self']);
                $return[$k]['unsafe-inline'] =
                    ($return[$k]['unsafe-inline'] ?? false) || !empty($data['unsafe-inline']);
                $return[$k]['unsafe-eval'] =
                    ($return[$k]['unsafe-eval'] ?? false) || !empty($data['unsafe-eval']);

            } elseif ($k !== 'inherit') {
                $return[$k] = $data;
            }
        }
    }
    return $return;
}

/**
 * Expand a version string:
 *      5.4.19-RC1 => 5041901
 * 
 * @param string $strver
 * @return int
 */
function expand_version(string $strver): int
{
    if (\preg_match('#^([0-9]+)\.([0-9]+)\.([0-9]+)(?:[^0-9]*)([0-9]+)?$#', $strver, $m)) {
        if (!isset($m[4])) {
            return (
                (100 * $m[3]) +
                (10000 * $m[2]) +
                (1000000 * $m[1])
            );
        }
        return (
            ($m[4] - 100) +
            (100 * $m[3]) +
            (10000 * $m[2]) +
            (1000000 * $m[1])
        );
    }
    return 0;
}

/**
 * Get all of the parent classes that a particular class inherits from
 *
 * @param string $class - Class name
 * @return array
 */
function get_ancestors(string $class): array
{
    $classes = [$class];
    while ($class = \get_parent_class($class)) {
        $classes[] = $class;
    }
    return $classes;
}

/**
 * Get the namespace of the method that called the one that called 
 * get_caller_namespace()
 * 
 * @param int $offset
 * @return string
 */
function get_caller_namespace(int $offset = 0): string
{
    $dbg = \array_values(
        \array_slice(
            \debug_backtrace(),
            1
        )
    );
    if (!empty($dbg[$offset]['object'])) {
        $class = \get_class($dbg[$offset]['object']);
        $temp = \explode('\\', $class);
        \array_pop($temp);
        return \implode('\\', $temp);
    }
    return '\\';
}

/**
 * Get a database class
 * 
 * @staticvar array $_cache
 * 
 * @param string $id Database identifier
 * @return Database
 * 
 * @throws DBException
 */
function get_database(string $id = 'default'): Database
{
    static $_cache = [];
    if (isset($_cache[$id])) {
        return $_cache[$id];
    }
    
    $state = State::instance();
    if (isset($state->database_connections[$id])) {
        if (\count($state->database_connections[$id]) === 1) {
            $k = \array_keys($state->database_connections[$id])[0];
            $_cache[$id] = $state->database_connections[$id][$k];
        } else {
            $r = \random_int(0, \count($state->database_connections[$id]) - 1);
            $k = \array_keys($state->database_connections[$id])[$r];;
            $_cache[$id] = $state->database_connections[$id][$k];
        }
        return $_cache[$id];
    }
    throw new DBException(
        \trk('errors.database.not_found', $id)
    );
}

/**
 * Get a base URL for a gravatar image
 *
 * @param string $email
 * @return string
 */
function get_gravatar_url(string $email): string
{
    return 'https://www.gravatar.com/avatar/'.\md5(\strtolower(\trim($email)));
}

/**
 * Get a ReCAPTCHA object configured to use
 *
 * @param string $secretKey
 * @param array $opts
 * @return ReCaptcha
 */
function getReCaptcha(string $secretKey, array $opts = []): ReCaptcha
{
    $state = State::instance();
    $opts = $opts + $state->universal['guzzle'];

    // Forcefully route this over Tor
    if ($state->universal['tor-only']) {
        $opts[CURLOPT_PROXY] = 'http://127.0.0.1:9050/';
        $opts[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
    }
    $curlPost = new CurlPost(null, $opts);

    return new ReCaptcha($secretKey, $curlPost);
}

/**
 * Is a particular function disabled?
 *
 * @param string $function
 * @return boolean
 */
function is_disabled(string $function): bool
{
    static $disabled = null;
    if ($disabled === null) {
        $disabled = \explode(',', \ini_get('disable_functions'));
    }
    return \in_array($function, $disabled);
}

/**
 * Output a JSON response, terminate script execution.
 *
 * @param mixed $result
 */
function json_response($result)
{
    if (!\headers_sent()) {
        \header("Content-Type: application/json");
    }
    die(
        \json_encode($result, JSON_PRETTY_PRINT)
    );
}

/**
 * Return a subset of the keys of the source array
 *
 * @param array $source
 * @param array $keys
 * @return array
 */
function keySlice(array $source, array $keys = []): array
{
    return \array_intersect_key(
        $source,
        \array_flip(
            \array_values($keys)
        )
    );
}

/**
 * List all the files in a directory (and subdirectories)
 *
 * @param string $folder - start searching here
 * @param string $extension - extensions to match
 * 
 * @return array
 */
function list_all_files(string $folder, string $extension = '*'): array
{
    $dir = new \RecursiveDirectoryIterator($folder);
    $ite = new \RecursiveIteratorIterator($dir);
    if ($extension === '*') {
        $pattern = '/.*/';
    } else {
        $pattern = '/.*\.' . \preg_quote($extension, '/') . '$/';
    }
    $files = new \RegexIterator($ite, $pattern, \RegexIterator::GET_MATCH);
    $fileList = [];
    foreach($files as $file) {
        if (\is_array($file)) {
            foreach ($file as $i => $f) {
                // Prevent . and .. from being treated as valid files:
                $check = \preg_replace('#^(.+?)/([^/]+)$#', '$2', $f);
                if ($check === '.' || $check === '..') {
                    unset($file[$i]);
                }
            }
        }
        $fileList = \array_merge($fileList, $file);
    }
    return $fileList;
}

/**
 * Load a JSON file and parses it
 *
 * @param string $file - The absolute path of the file name
 * @return mixed
 * @throws AccessDenied
 * @throws FileNotFound
 */
function loadJSON(string $file)
{
    // Very specific checks
    if (!\file_exists($file)) {
        throw new FileNotFound($file);
    }
    if (!\is_readable($file)) {
        throw new AccessDenied($file);
    }
    // The meat of this function is kind of boring:
    return \Airship\parseJSON(
        \file_get_contents($file),
        true
    );
}

/**
 * Parser for JSON with comments
 *
 * @param string $json JSON text
 * @param boolean $assoc Return as an associative array?
 * @param int $depth Maximum depth
 * @param int $options options
 * @return mixed
 */
function parseJSON(
    string $json,
    bool $assoc = false,
    int $depth = 512,
    int $options = 0
) {
    return \json_decode(
        \preg_replace(
            '#(/\*([^*]|[\r\n]|(\*+([^*/]|[\r\n])))*\*+/)|([\s\t]//.*)|(^//.*)#',
            '',
            $json
        ),
        $assoc,
        $depth,
        $options
    );
}

/**
 * Redirect the user to a given URL. Optionally pass GET parameters.
 * 
 * @param string $destination The URL to redirect the user to
 * @param array $params - GET parameters
 * @return void
 */
function redirect(
    string $destination,
    array $params = []
) {
    if (empty($params)) {
        \header('Location: '.$destination);
    } else {
        \header('Location: '.$destination.'?'.\http_build_query($params));
    }
    exit;
}

/**
 * Fetch a query string from the stored queries file
 *
 * @param string $index Which index to replace
 * @param string $driver Which database driver
 * @param array $params Parameters to be replaced in the query string?
 * @return string
 * @throws NotImplementedException
 */
function queryStringRoot(string $index, string $driver = '', array $params = []): string
{
    return \Airship\queryString($index, $params, '', $driver);
}

/**
 * Fetch a query string from the stored queries file
 *
 * @param string $index Which index to replace
 * @param array $params Parameters to be replaced in the query string
 * @param string $cabin Which Cabin are we loading?
 * @param string $driver Which database driver?
 * @return string
 * @throws NotImplementedException
 */
function queryString(string $index, array $params = [], string $cabin = \CABIN_NAME, string $driver = ''): string
{
    static $_cache = [];
    if (empty($driver)) {
        $db = \Airship\get_database();
        $driver = $db->getDriver();
    }
    $cacheKey = \Sodium\bin2hex(
        \Sodium\crypto_generichash(
            $cabin . '/' . $driver,
            '',
            \Sodium\CRYPTO_GENERICHASH_BYTES_MIN
        )
    );
    if (empty($_cache[$cacheKey])) {
        $driver = preg_replace('/[^a-z]/', '', \strtolower($driver));
        $path = !empty($cabin)
            ? ROOT . '/Cabin/' . $cabin.'/Queries/' . $driver . '.json'
            : ROOT . '/Engine/Queries/' . $driver . '.json';
        $_cache[$cacheKey] = \Airship\loadJSON($path);
    }
    $split_key = explode('.', $index);
    $v = $_cache[$cacheKey];
    foreach ($split_key as $k) {
        if (!\array_key_exists($k, $v)) {
            throw new NotImplementedException(
                \trk('errors.database.query_not_found', $index)
            );
        }
        $v = $v[$k];
    }
    if (\is_array($v)) {
        throw new NotImplementedException(
            \trk('errors.database.multiple_candidates', $index)
        );
    }
    $str = $v;
    foreach ($params as $token => $replacement) {
        $str = \str_replace('{{'.$token.'}}', $replacement, $str);
    }
    return $str;
}

/**
 * Determine the valid slug for a given title, before de-duplication
 *
 * @param string $title
 * @return string
 */
function slugFromTitle(string $title): string
{
    $slug = \preg_replace('#[^A-Za-z0-9]#', '-', \strtolower($title));
    return \trim(\preg_replace('#\-{2,}#', '-', $slug), '-');
}

/**
 * Convert an
 *
 * @param \Throwable $ex
 * @return array
 */
function throwableToArray(\Throwable $ex): array
{
    return [
        'line' => $ex->getLine(),
        'file' => $ex->getFile(),
        'message' => $ex->getMessage(),
        'code' => $ex->getCode(),
        'trace' => $ex->getTrace()
    ];
}

/**
 * Invoke all of the tighten[Boltnamegoeshere]Bolt() methods automatically
 *
 * @param object $obj
 */
function tightenBolts($obj)
{
    $class = \get_class($obj);
    foreach (\get_class_methods($class) as $method) {
        if (\preg_match('/^tighten([A-Za-z0-9_]*)Bolt$/', $method)) {
            $obj->$method();
        }
    }
}

/**
 * Create a unique ID (e.g. for perma-links)
 *
 * @param int $length
 * @return string
 */
function uniqueId(int $length = 24): string
{
    if ($length < 1) {
        return '';
    }
    $n = (int) ceil($length * 0.75);
    $str = \random_bytes($n);
    return Base64UrlSafe::encode($str);
}