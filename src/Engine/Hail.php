<?php
declare(strict_types=1);
namespace Airship\Engine;

use \Psr\Http\Message\ResponseInterface;
use \GuzzleHttp\ClientInterface;

class Hail
{
    protected $client;
    protected $keyCache;
    
    /**
     * 
     * \GuzzleHttp\Client $client
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }
    
    /**
     * Perform a GET request
     * 
     * @param string $url
     * @param array $params
     * @return ResponseInterface
     */
    public function get(string $url, array $params = [])
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
    public function getAsync(string $url, array $params = [])
    {
        return $this->client->getAsync(
            $url,
            $this->params($params, $url)
        );
    }
    
    /**
     * Perform a GET request
     * 
     * @param string $url
     * @param array $params
     *
     * @return ResponseInterface
     */
    public function post(string $url, array $params = [])
    {
        return $this->client->post(
            $url,
            $this->params($params, $url)
        );
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
    public function downloadFile(string $url, string $filename, array $params = [])
    {
        $fp = \fopen($filename, 'wb');
        $opts = $this->params($params, $url);
        
        $opts[CURLOPT_FOLLOWLOCATION] = true;
        $opts[CURLOPT_FILE] = $fp;
        
        $result = $this->client->post($url, $opts);
        
        \fclose($fp);
        return $result;
    }
    
    /**
     * Perform a GET request, asynchronously
     * 
     * @param string $url
     * @param array $params
     * @return ResponseInterface
     */
    public function postAsync(string $url, array $params = [])
    {
        return $this->client->postAsync(
            $url,
            $this->params($params, $url)
        );
    }
    
    /**
     * Make sure we include the default params
     * 
     * @param array $params
     * @param string $url (used for decision-making)
     * 
     * @return array
     */
    public function params(array $params = [], string $url = '') : array
    {
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
        $matches = [];
        if (\preg_match('#^https://([^/:]+)\.onion:(?:([0-9]+))#', $url, $matches)) {
            if (\preg_match('#\.onion#', $matches[0])) {
                // A .onion domain should be a Tor Hidden Service
                $defaults['curl'][CURLOPT_PROXY] = 'http://127.0.0.1:9050/';
                $defaults['curl'][CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
                // Don't force HTTPS
                unset($defaults['curl'][CURLOPT_SSLVERSION]);
            }
        } elseif (\preg_match('#^https?://([^/]+)\.onion#', $this->client->getConfig('base_uri') ?? '')) {
            // Use Tor
            $defaults['curl'][CURLOPT_PROXY] = 'http://127.0.0.1:9050/';
            $defaults['curl'][CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
        } elseif (!empty($config->universal['tor-only'])) {
            // We were configured to use TOR for everything.
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
}
