<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum;

use \Airship\Alerts\Continuum\InvalidConfig;

/**
 * Hail - versioned API schema
 */
abstract class API
{
    const API_VERSION = '1.0.0';
    
    /**
     * Get an API endpoint
     * 
     * @param string $key
     * @param string $version
     * @return string
     */
    public static function get(string $key, $version = self::API_VERSION): string {
        static $cache = [];
        
        if (empty($cache[$version])) {
            $cache[$version] = self::getAll($version);
        }
        
        return (
            isset($cache[$version][$key])
                ? $cache[$version][$key]
                : ''
        );
    }
    
    /**
     * Get the entire API for a specific version
     * 
     * @param string $version
     * @return array
     * @throws InvalidConfig
     */
    public static function getAll(string $version = self::API_VERSION): array
    {
        switch ($version) {
            case '1.0.0':
                return [
                    'airship_download' => '/airship_download',
                    'airship_version' => '/airship_version',
                    'fetch_keys' => '/keyggdrasil',
                    'version' => '/version',
                    'download' => '/download'
                ];
            default:
                throw new InvalidConfig(
                    \trk('errors.hail.invalid_api_version', $version)
                );
        }
    }
}