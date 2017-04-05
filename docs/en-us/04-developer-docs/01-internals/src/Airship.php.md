# src/Airship.php

**Namespace**: `\Airship`

## Description

This file defines a few constants and functions inside the `Airship` namespace
to be used anywhere.

## Constants

### `AIRSHIP_VERSION`

Type: `string`
Purpose: The current Airship version.

### `AIRSHIP_BLAKE2B_PERSONALIZATION`

Type: `string`
Purpose: This is used by [Keyggdrasil](Engine/Keyggdrasil.php.md) (and related
         components) to personalize the BLAKE2b hashes used in the Merkle tree.

## Functions

### `\Airship\all_keys_exist()`

**Return type**: `bool`

Returns true if all the keys in `$keys` were found in `$haystack`.

**Parameters**:

* `array $keys = []`
  * The keys we are searching for in `$haystack`
* `array $haystack = []`
  * The array we are searching

### `\Airship\array_from_http_query()`

**Return type**: `array`

The inverse of PHP's `http_build_query()`. Safely uses `parse_str()`.

**Parameters**:

* `string $queryString = ''`
  * The HTTP query string

### `\Airship\array_multi_diff()`

**Return type**: `array`

Calculate a diff between two multidimensional arrays.

**Parameters**:

* `array $new`
  * The "new" array.
* `array $old`
  * The "old" array.

### `\Airship\autoload()`

**Return type**: `bool`

Register a PSR-4 autoloader for a given namespace and directory. Allows basic
escaping. For example: `~/Cabins` expands the `~` to the real path of `src`.

**Parameters**:

* `string $namespace`
  * The namespace we are autoloading.
* `string $directory`
  * The directory we should search for files.

### `\Airship\chunk()`

**Return type**: `array`

Split a string based on a token, while ignoring leading and trailing tokens.

For example: `\Airship\chink('#test#123', '#')` yields `['test', '123']`, not
`['', 'test', '123].

**Parameters**:

* `string $str`
  * The string we are breaking into chunks.
* `string $token`
  * The token to use for splitting the string.

### `\Airship\configWriter()`

**Return type:** `\Twig_Environment`

Loads a minimal `Twig_Environment` for rendering configuration files from a
configuration template. The only filter we add is `je` which wraps the `je`
Lens function.

**Parameters**:

* `string $rootDir`
  * The directory to load templates from

### `\Airship\csp_merge()`

**Return type**: `array`

Merge multiple CSP-Builder configuration files. This is currently used for
handling CSP inheritance from the global policy. In the future, we foresee it
being useful for creating per-page CSP exceptions.

**Parameters**:

* `array ...$policies`
  * As many policies as we need to merge.

### `\Airship\expand_version()`

** Return type**: `int`

Expand a version string to a comparable integer. e.g. `5.4.19-RC1` => `5041901`

**Parameters**:

* `string $version`
  * The version string. Should be a semantic version (`x.y.z`).

### `\Airship\get_ancestors()`

**Return type**: `array`

Get all of the parent classes that a particular class inherits from.

**Parameters**:

* `string $class`
  * The name of the class we are looking up.

### `\Airship\get_caller_namespace()`

**Return type**: `string`

This is best demonstrated by way of example:

```php
namespace Foo {
    class A {
        public function get()
        {
            var_dump(\Airship\get_caller_namespace());
        }
    }
}
namespace Bar {
    $x = (new Foo\A())->get();
}
```

This should output `string(3) "Bar"`.

**Parameters**:

* `int $offset = 0`
  * How many steps back should we look?

### `\Airship\get_database()`

**Return type**: `\Airship\Engine\Database`

Returns the active database established by the Airship bootstrapping process
by default. You can pass in an identifier to select a different database if
you have one configured.

For example:

```php
$db = \Airship\get_database('sqlite_1');
$db->run("SELECT * FROM foo WHERE id = ?", 3);
```

**Parameters**:

* `string $id = 'default'`
  * Which configured database to select?

### `\Airship\get_gravatar_url()`

**Return type**: `string`

Returns a Gravatar URL for a given email address.

**Parameters**:

* `string $email`
  * Your email address

### `\Airship\getRecaptcha()`

**Return type**: `ReCaptcha\ReCaptcha`

This returns an forked instance of the standard `ReCaptcha` class. Our fork
adds support for HTTP and SOCKS proxies (i.e. Tor). If your Airship is
configured in Tor-only mode, this class will automatically send the ReCAPTCHA
API request over the Tor network instead of exposing your server's IP address
to Google.

**Parameters**:

* `string $secretKey`
  * Your secret key for the ReCAPTCHA API
* `array $opts = []`
  * An array of cURL options

### `\Airship\is_disabled()`

**Return type**: `bool`

Returns TRUE if a particular function is disabled in php.ini

**Parameters**:

* `string $function`
  * The name of the function to check

### `\Airship\json_response()`

This function outputs the parameters you pass into a prettied JSON output, then
*terminates PHP execution*. Optionally, you can supply a [`SignatureSecretKey`](https://github.com/paragonie/halite/blob/master/doc/Classes/Asymmetric/SignatureSecretKey.md)
or [`SignatureKeyPair`](https://github.com/paragonie/halite/blob/master/doc/Classes/SignatureKeyPair.md)
and your JSON message will be signed with Ed25519. The signature is encoded
with RFC 4648 Base64UrlSafe encoding and will prepend the JSON message, be
followed by a newline character, and will authenticate the prettied JSON
message (sans the preceding newline character).

If headers haven't been sent, this also sends a Content-Type header:

    Content-Type: application/json

**Parameters**:

* `$result`
  * Any variable that can be serialized by `json_encode()`
* `$signingKey = null`
  * An instance of [`SignatureSecretKey`](https://github.com/paragonie/halite/blob/master/doc/Classes/Asymmetric/SignatureSecretKey.md)
    or [`SignatureKeyPair`](https://github.com/paragonie/halite/blob/master/doc/Classes/SignatureKeyPair.md)

### `\Airship\keySlice()`

**Return type**: `array`

Returns a subset of the keys of the source array. For example:

```php
$arr = [
    'abc' => 'foo!',
    'def' => 'bar?',
    'ghi' => 'baz...'
];
var_dump(\Airship\keySlice($arr, ['abc', 'ghi']));
```

This will produce:

```
array(2) {
  ["abc"]=>
  string(4) "foo!"
  ["ghi"]=>
  string(6) "baz..."
}
```

**Parameters**:

* `array $source`
  * The array we are slicing
* `array $keys = []`
  * The keys to select

### `\Airship\list_all_files()`

**Return type**: `array`

List all the files in a directory (and subdirectories)

**Parameters**:

* `string $folder`
  * Where to begin our search
* `string $extension = '*'`
  * Optionally filter based on file extension

### `\Airship\loadJSON()`

**Return type**: mixed
*Throws* `\Airship\Alerts\FileSystem\AccessDenied`
*Throws* `\Airship\Alerts\FileSystem\FileNotFound`

Load a commented JSON file and parse it.

**Parameters**:

* `string $file`
  * The file to load and parse.

### `\Airship\parseJSON()`

**Return type**: mixed

Parse a commented JSON file.

**Parameters**:

* `string $json`
  * The string we are parsing
* `bool $assoc = false`,
  * Return an associative array instead of an object?
* `int $depth`
  * Maximum deptyh
* `int $options`
  * See [`json_decode()`](https://secure.php.net/json_decode) for supported
    options.

### `\Airship\path_to_filename()`

**Return type**: `string`

Given a file path, only return the file name. Optionally, trim the extension.

**Parameters**:

* `string $fullPath`
  * The full path to the file
* `bool $trimExtension = false`
  * Should we trim the file extension?

### `\Airship\redirect()`

Serve an HTTP 301 redirect to the destination URL. If you pass an array, this
function will build the GET parameters for you.

**Parameters**:

* `string $destination`
  * The URL we are redirecting the user towards.
* `array $params = []`
  * Builds a query string and appends it to the destination URL.

### `\Airship\queryString()`

**Return type**: `string`

Fetch a query string from the stored queries file. This exists
because many RDBMSes deviate from the SQL standard in their own weird ways.

For example,

 * PostgreSQL: `SELECT foo FROM bar ORDER BY baz OFFSET 10 LIMIT 5`
 * MySQL: `SELECT foo FROM bar ORDER BY baz LIMIT 5, 10`
 * SQL Server: `SELECT foo FROM bar ORDER BY baz OFFSET 10 ROWS FETCH NEXT 5 ROWS ONLY`

To work around various syntax quirks, we store the statements in JSON files
and reference them by `key.subkey.another`.

**Parameters**:

* `string $index`
  * Which query to use? Decomposes `.` into child array indices.
* `array $params = []`
  * Replace `{{ key }}` with the respective value in the query string.
* `string $cabin = \CABIN_NAME`
  * Which Cabin are we looking in for the query string? Defaults to the
    current active Cabin.
* `string $driver = ''`
  * Which DB driver are we using?

### `\Airship\queryStringRoot()`

**Return type**: `string`

Fetch a query string from the universal stored queries file.
See `\Airship\queryString()` above for more information.

* `string $index`
  * Which query to use? Decomposes `.` into child array indices.
* `string $driver = ''`
  * Which DB driver are we using?
* `array $params = []`
  * Replace `{{ key }}` with the respective value in the query string.

### `\Airship\saveJSON()`

**Return type**: `bool`

Save data as a pretty JSON document.

**Parameters**:

* `string $file`
  * Where to save the file
* `mixed $data`
  * The data we are seralizing and saving

### `\Airship\secure_shuffle()`

Securely shuffle an array, using the Fisher-Yates algorithm with a CSPRNG.
See: [Common Uses for CSPRNGs](https://paragonie.com/blog/2015/07/common-uses-for-csprngs-cryptographically-secure-pseudo-random-number-generators#shuffle)

**Parameters**:

* `array &$array`
  * A reference to an array. It will be shuffled in-place.

### `\Airship\slugFromTitle()`

**Return type**: `string`

Determine the valid slug for a given title, before de-duplication.

**Parameters**:

* `string $title`
  * The user-provided title we are converting to a slug.

### `\Airship\tempnam()`

Like PHP's [`tempnam()`](https://secure.php.net/tempnam) but allows you to specify the file extension.

**Parameters**:

* `string $prefix = 'airship-'`
  * The prefix for this temporary file.
* `string $ext = ''`
  * Optional file extension to add to the temporary file.
* `string $dir = ''`
  * Where to save the temporary file? Defaults to the system's temporary files
    directory.

### `\Airship\throwableToArray()`

**Return type**: `array`

Convert an Exception or Error into an array (for logging)

**Parameters**:

* `\Throwable $ex`
  * The `\Exception` or `\Error` we are converting.

### `\Airship\tightenBolts()`

Invoke all of the `tighten[BoltNameGoesHere]Bolt()` methods.

**Parameters**:

* `$obj`
  * An object that, presumably, possesses a `Bolt` with a
    `tightenBoltnameBolt()` method.

### `\Airship\uniqueId()`

Creates a unique ID (Base64UrlSafe-encoded random string).

**Parameters**:

* `int $length = 24`
  * How long do we want our Unique ID to be?

