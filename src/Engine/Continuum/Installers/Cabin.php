<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum\Installers;

use \Airship\Engine\Continuum\Installer as BaseInstaller;
use \Airship\Engine\Continuum\Sandbox;
use \ParagonIE\ConstantTime\Base64UrlSafe;

/**
 * Class Cabin
 *
 * This allows a new Cabin to be installed.
 *
 * @package Airship\Engine\Continuum\Installer
 */
class Cabin extends BaseInstaller
{
    protected $type = 'Cabin';
    protected $ext = 'phar';

    /**
     * @param InstallFile $fileInfo
     * @return bool
     */
    public function install(InstallFile $fileInfo): bool
    {
        $ns = $this->makeNamespace($this->supplier->getName(), $this->package);
        $alias = 'cabin.' . $this->supplier->getName() . '.' . $this->package . '.phar';
        $updater = new \Phar(
            $fileInfo->getPath(),
            \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::KEY_AS_FILENAME
        );
        $updater->setAlias($alias);

        // Overwrite files
        $updater->extractTo(ROOT . '/Cabin/' . $ns);

        // Run the update trigger.
        Sandbox::safeRequire('phar://' . $alias . '/update_trigger.php');

        // Free up the updater alias
        $garbageAlias = Base64UrlSafe::encode(\random_bytes(33)) . '.phar';
        $updater->setAlias($garbageAlias);
        unset($updater);

        return true;
    }

    public function clearCache(): bool
    {

    }

    /**
     * some-test-user/cabin--for-the-win =>
     * Some_Test_User__Cabin_For_The_Win
     *
     * @param string $supplier
     * @param string $cabin
     * @return string
     */
    protected function makeNamespace(string $supplier, string $cabin): string
    {
        $supplier = \preg_replace('/[^A-Za-z0-9_]/', '_', $supplier);
        $exp = \explode('_', $supplier);
        $supplier = \implode('_', \array_map('ucfirst', $exp));
        $supplier = \preg_replace('/_{2,}/', '_', $supplier);

        $cabin = \preg_replace('/[^A-Za-z0-9_]/', '_', $cabin);
        $exp = \explode('_', $cabin);
        $cabin = \implode('_', \array_map('ucfirst', $exp));
        $cabin = \preg_replace('/_{2,}/', '_', $cabin);

        return \implode('__',
            [
                \trim($supplier, '_'),
                \trim($cabin, '_')
            ]
        );
    }
}
