<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum;

/**
 * Class Version
 *
 * This contains the version comparison logic.
 *
 * @package Airship\Engine\Continuum
 */
class Version
{
    const GROUP_MAJOR     = 1000000;
    const GROUP_MINOR     =   10000;
    const GROUP_PATCH     =     100;
    const GROUP_INCREMENT =       1;
    
    protected $currentVersion;

    /**
     * Version constructor.
     * @param string $currentVersion
     */
    public function __construct(string $currentVersion)
    {
        $this->currentVersion = $currentVersion;
    }
    
    /**
     * Is $nextVersion newer than $this->currentVersion?
     * 
     * @param string $nextVersion
     * @return bool
     */
    public function isUpgrade(string $nextVersion): bool
    {
        $curr = \Airship\expand_version($this->currentVersion);
        $next = \Airship\expand_version($nextVersion);
        
        return $next > $curr;
    }
    
    /**
     * Is this a major upgrade? (Semantic versioning)
     * 
     * @param string $nextVersion
     * @return bool
     */
    public function isMajorUpgrade(string $nextVersion): bool
    {
        $curr = \Airship\expand_version($this->currentVersion);
        $next = \Airship\expand_version($nextVersion);
        
        return (
            self::getGroup($next, self::GROUP_MAJOR)
                >
            self::getGroup($curr, self::GROUP_MAJOR) 
        );
    }
    
    /**
     * Is this a minor upgrade? (Semantic versioning)
     * 
     * @param string $nextVersion
     * @return bool
     */
    public function isMinorUpgrade(string $nextVersion): bool
    {
        $curr = \Airship\expand_version($this->currentVersion);
        $next = \Airship\expand_version($nextVersion);
        
        return (
            self::getGroup($next, self::GROUP_MAJOR) === self::getGroup($curr, self::GROUP_MAJOR)
                && 
            self::getGroup($next, self::GROUP_MINOR) > self::getGroup($curr, self::GROUP_MINOR)
        );
        
    }
    
    /**
     * Is this a patch upgrade? (Semantic versioning)
     * 
     * @param string $nextVersion
     * @return bool
     */
    public function isPatchUpgrade(string $nextVersion): bool
    {
        $curr = \Airship\expand_version($this->currentVersion);
        $next = \Airship\expand_version($nextVersion);
        
        return (
            self::getGroup($next, self::GROUP_MAJOR) === self::getGroup($curr, self::GROUP_MAJOR)
                && 
            self::getGroup($next, self::GROUP_MINOR) === self::getGroup($curr, self::GROUP_MINOR)
                && 
            self::getGroup($next, self::GROUP_PATCH) > self::getGroup($curr, self::GROUP_PATCH)
        );
    }
    
    /**
     * Get the value within a group (i.e. 1.0.3 => 1000300)
     * 
     * @param int $value An expanded version
     * @param int $group
     * @return int
     */
    public static function getGroup(int $value, int $group = self::GROUP_INCREMENT): int
    {
        switch ($group) {
            // [W...W]XXYYZZ
            case self::GROUP_MAJOR:
                $left = $value - ($value % self::GROUP_MAJOR);
                return intdiv($left, self::GROUP_MAJOR);
            // W...W[XX]YYZZ
            case self::GROUP_MINOR:
                $left = ($value - ($value % self::GROUP_MINOR));
                $left %= self::GROUP_MAJOR;
                return intdiv($left, self::GROUP_MINOR);
            // W...WXX[YY]ZZ
            case self::GROUP_PATCH:
                $left = $value - ($value % self::GROUP_PATCH);
                $left %= self::GROUP_MINOR;
                return intdiv($left, self::GROUP_PATCH);
            default:
                throw new \InvalidArgumentException(
                    __METHOD__.': $group must be a constant'
                );
        }
    }

    /**
     * Get the expected next version, based on upgrade type.
     *
     *  From '1.4.11':
     *    (self::GROUP_PATCH) -> '1.4.12'
     *    (self::GROUP_MINOR) -> '1.5.0'
     *    (self::GROUP_MAJOR) -> '2.0.0'
     *
     * @param int $type
     * @return string
     */
    public function getUpgrade(int $type = self::GROUP_PATCH): string
    {
        $value = \Airship\expand_version($this->currentVersion);
        $value += $type;
        $value -= ($value % $type);
        return \implode('.', [
            self::getGroup($value, self::GROUP_MAJOR),
            self::getGroup($value, self::GROUP_MINOR),
            self::getGroup($value, self::GROUP_PATCH)
        ]);

    }
}
