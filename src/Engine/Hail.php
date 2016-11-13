<?php
declare(strict_types=1);
namespace Airship\Engine;

use Airship\Alerts\Hail\SignatureFailed;
use Airship\Engine\Continuum\Supplier;
use GuzzleHttp\{
    Client,
    ClientInterface,
    Exception\TransferException,
    Psr7\Response
};
use ParagonIE\ConstantTime\{
    Base64,
    Base64UrlSafe,
    Binary
};
use ParagonIE\Halite\Asymmetric\{
    Crypto as Asymmetric,
    SignaturePublicKey
};
use Psr\Http\Message\ResponseInterface;

/**
 * Class Hail
 *
 * Abstracts away the network communications; silently enforces configuration
 * (e.g. tor-only mode).
 *
 * @package Airship\Engine
 */
class Hail
{
    const ENCODED_SIGNATURE_LENGTH = 88;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var Supplier[]
     */
    protected $supplierCache;
    
    /**
     * @param \GuzzleHttp\ClientInterface $client
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
        if (IDE_HACKS) {
            $this->client = new Client();
        }
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'description' =>
                'Tor-compatible HTTP client that enforces HTTPS'
        ];
    }

    /**
     * Download a file over the Internet
     *
     * @param string $url - the URL to request
     * @param string $filename - the name of the local file path
     * @param array $params
     *
     * @return ResponseInterface
     */
    public function downloadFile(
        string $url,
        string $filename,
        array $params = []
    ): ResponseInterface {
        $fp = \fopen($filename, 'wb');
        $opts = $this->params($params, $url);

        $opts[\CURLOPT_FOLLOWLOCATION] = true;
        $opts[\CURLOPT_FILE] = $fp;

        $result = $this->client->post($url, $opts);

        \fclose($fp);
        return $result;
    }
    
    /**
     * Perform a GET request
     * 
     * @param string $url
     * @param array $params
     * @return ResponseInterface
     */
    public function get(string $url, array $params = []): ResponseInterface
    {
        return $this->client->get(
            $url,
            $this->params($params, $url)
        );
    }

    /**
     * Perform a GET request, asynchronously
     *
     * @param string $url
     * @param array $params
     * @return ResponseInterface
     */
    public function getAsync(
        string $url,
        array $params = []
    ): ResponseInterface {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->client->getAsync(
            $url,
            $this->params($params, $url)
        );
    }

    /**
     * Perform a GET request, get a decoded JSON response.
     *
     * @param string $url
     * @param array $params
     * @return mixed
     */
    public function getJSON(string $url, array $params = [])
    {
        return \Airship\parseJSON(
            $this->getReturnBody($url, $params),
            true
        );
    }

    /**
     * Perform a POST request, get the body
     *
     * @param string $url
     * @param array $params
     * @return string
     * @throws TransferException
     */
    public function getReturnBody(
        string $url,
        array $params = []
    ): string {
        $response = $this->client->get(
            $url,
            $this->params($params, $url)
        );
        $code = $response->getStatusCode();
        if ($code >= 200 && $code < 300) {
            return (string) $response->getBody();
        }
        throw new TransferException();
    }
    /**
     * Perform a GET request, get a decoded JSON response.
     * Internally verifies an Ed25519 signature.
     *
     * @param string $url
     * @param SignaturePublicKey $publicKey
     * @param array $params
     * @return mixed
     */
    public function getSignedJSON(
        string $url,
        SignaturePublicKey $publicKey,
        array $params = []
    ) {
        $response = $this->client->get(
            $url,
            $this->params($params, $url)
        );
        if ($response instanceof Response) {
            return $this->parseSignedJSON($response, $publicKey);
        }
        return null;
    }

    /**
     * Make sure we include the default params
     *
     * @param array $params
     * @param string $url (used for decision-making)
     *
     * @return array
     */
    public function params(
        array $params = [],
        string $url = ''
    ): array {
        $config = State::instance();
        $defaults = [
            'curl' => [
                // Force TLS 1.2
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
            ]
        ];

        /**
         * If we have pre-configured some global parameters, let's add them to
         * our defaults before we check if we need Tor support.
         */
        if (!empty($config->universal['guzzle'])) {
            $defaults = \array_merge(
                $defaults,
                $config->universal['guzzle']
            );
        }

        /**
         * Support for Tor Hidden Services
         */
        if (\Airship\isOnionUrl($url)) {
            // A .onion domain should be a Tor Hidden Service
            $defaults['curl'][CURLOPT_PROXY] = 'http://127.0.0.1:9050/';
            $defaults['curl'][CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
            if (!\preg_match('#^https://', $url)) {
                // If it's a .onion site, HTTPS is not required.
                // If HTTPS is specified, still enforce it.
                unset($defaults['curl'][CURLOPT_SSLVERSION]);
            }
        } elseif (!empty($config->universal['tor-only'])) {
            // We were configured to use Tor for everything.
            $defaults['curl'][CURLOPT_PROXY] = 'http://127.0.0.1:9050/';
            $defaults['curl'][CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
        }

        return \array_merge(
            $defaults,
            [
                'form_params' => $params
            ]
        );
    }

    /**
     * Parse a signed JSON response
     *
     * @param Response $response
     * @return mixed
     * @throws SignatureFailed
     * @throws TransferException
     */
    public function parseJSON(Response $response)
    {
        $code = $response->getStatusCode();
        if ($code >= 200 && $code < 300) {
            $body = (string) $response->getBody();
            return \Airship\parseJSON($body, true);
        }
        throw new TransferException();
    }

    /**
     * Parse a signed JSON response
     *
     * @param Response $response
     * @param SignaturePublicKey $publicKey
     * @return mixed
     * @throws SignatureFailed
     * @throws TransferException
     */
    public function parseSignedJSON(Response $response, SignaturePublicKey $publicKey)
    {
        $code = $response->getStatusCode();
        if ($code >= 200 && $code < 300) {
            $body = (string) $response->getBody();
            $firstNewLine = \strpos($body, "\n");
            // There should be a newline immediately after the base64urlsafe-encoded signature
            if ($firstNewLine !== self::ENCODED_SIGNATURE_LENGTH) {
                throw new SignatureFailed(
                    \sprintf(
                        "First newline found at position %s, expected %d.\n%s",
                        \print_r($firstNewLine, true),
                        \print_r(self::ENCODED_SIGNATURE_LENGTH, true),
                        Base64::encode($body)
                    )
                );
            }
            $sig = Base64UrlSafe::decode(
                Binary::safeSubstr($body, 0, 88)
            );
            $msg = Binary::safeSubstr($body, 89);
            if (!Asymmetric::verify($msg, $publicKey, $sig, true)) {
                throw new SignatureFailed();
            }
            return \Airship\parseJSON($msg, true);
        }
        throw new TransferException();
    }

    /**
     * Perform a POST request
     *
     * @param string $url
     * @param array $params
     *
     * @return ResponseInterface
     */
    public function post(
        string $url,
        array $params = []
    ): ResponseInterface {
        return $this->client->post(
            $url,
            $this->params($params, $url)
        );
    }
    
    /**
     * Perform a GET request, asynchronously
     * 
     * @param string $url
     * @param array $params
     * @return ResponseInterface
     */
    public function postAsync(
        string $url,
        array $params = []
    ): ResponseInterface {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->client->postAsync(
            $url,
            $this->params($params, $url)
        );
    }

    /**
     * Perform a POST request, get a decoded JSON response.
     *
     * @param string $url
     * @param array $params
     * @return mixed
     */
    public function postJSON(string $url, array $params = [])
    {
        return \Airship\parseJSON(
            $this->postReturnBody($url, $params),
            true
        );
    }

    /**
     * Perform a POST request, get the body
     *
     * @param string $url
     * @param array $params
     * @return string
     * @throws TransferException
     */
    public function postReturnBody(
        string $url,
        array $params = []
    ): string {
        $response = $this->client->post(
            $url,
            $this->params($params, $url)
        );
        $code = $response->getStatusCode();
        if ($code >= 200 && $code < 300) {
            return (string) $response->getBody();
        }
        throw new TransferException();
    }

    /**
     * Perform a POST request, get a decoded JSON response.
     * Internally verifies an Ed25519 signature.
     *
     * @param string $url
     * @param SignaturePublicKey $publicKey
     * @param array $params
     * @return mixed
     */
    public function postSignedJSON(
        string $url,
        SignaturePublicKey $publicKey,
        array $params = []
    ) {
        $response = $this->client->post(
            $url,
            $this->params($params, $url)
        );
        if ($response instanceof Response) {
            return $this->parseSignedJSON($response, $publicKey);
        }
        return null;
    }
}
