<?php
declare(strict_types=1);
namespace Airship\Engine;

use \Airship\Alerts\TranslationKeyNotFound;

/**
 * Class Translation
 *
 * Registry Singleton for keeping track of application state
 *
 * @package Airship\Engine
 */
class Translation
{
    /**
     * @var array
     */
    private $phrases = [];
    
    /**
     * Lookup a string from a language file
     * 
     * @param string $key
     * @param string $lang
     * @param mixed[] ...$params
     * @return string
     * @throws TranslationKeyNotFound
     */
    public function lookup(
        string $key,
        string $lang = 'en-us',
        ...$params
    ): string {
        if (!\array_key_exists($lang, $this->phrases)) {
            $this->phrases[$lang] = \Airship\loadJSON(ROOT.'/lang/'.$lang.'.json');
        }
        $split_key = \explode('.', $key);
        $v = $this->phrases[$lang];
        foreach ($split_key as $k) {
            if (!\array_key_exists($k, $v)) {
                throw new TranslationKeyNotFound($key);
            }
            $v = $v[$k];
        }
        $str = '';
        while (empty($str)) {
            /** @noinspection PhpUsageOfSilenceOperatorInspection */
            $str = @\sprintf($v, ...$params);
            \array_push($params, '');
        }
        return $str;
    }

    /**
     * Literal translation (provided by gettext())
     *
     * @param string $message
     * @param string $domain
     * @return string
     */
    public function literal(
        string $message,
        string $domain = 'default'
    ): string {
        static $cacheDomain = '';
        if ($cacheDomain !== $domain) {
            \textdomain($domain);
            $cacheDomain = $domain;
        }
        return \gettext($message);
    }
    
    /**
     * Translate a string with gettext(), then format the output
     * 
     * @param string $text
     * @param mixed[] ...$params
     * @return string
     */
    public function format(string $text, ...$params)
    {
        return \sprintf(
            \gettext($text),
            ...$params
        );
    }
}
