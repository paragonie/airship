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
     * Clear the cache files related to Cabins.
     *
     * @return bool
     */
    public function clearCache(): bool
    {
        $name = $this->makeNamespace($this->supplier->getName(), $this->package);

        \unlink(
            \implode(
                DIRECTORY_SEPARATOR,
                [
                    ROOT,
                    'tmp',
                    'cache',
                    'cargo-' . $name . '.cache.json'
                ]
            )
        );
        \unlink(
            \implode(
                DIRECTORY_SEPARATOR,
                [
                    ROOT,
                    'tmp',
                    'cache',
                    'csp.' . $name . '.json'
                ]
            )
        );
        \unlink(
            \implode(
                DIRECTORY_SEPARATOR,
                [
                    ROOT,
                    'tmp',
                    'cache',
                    $name . '.motifs.json'
                ]
            )
        );
        \clearstatcache();
        return parent::clearCache();
    }

    /**
     * Create the default configuration
     *
     * @param string $nameSpace
     * @param array $metadata
     * @return bool
     */
    protected function configure(string $nameSpace, array $metadata = []): bool
    {
        if (!$this->defaultCabinConfig($nameSpace)) {
            return false;
        }
        if (!$this->createEmptyFiles($nameSpace)) {
            return false;
        }
        if (!$this->updateCabinsRegistry($nameSpace, $metadata)) {
            return false;
        }
        if (!$this->createSymlinks($nameSpace)) {
            return false;
        }
        return true;
    }

    /**
     * Create empty files (motifs.json, etc.)
     *
     * @param string $nameSpace
     * @return bool
     */
    protected function createEmptyFiles(string $nameSpace): bool
    {
        $dir = \implode(
            DIRECTORY_SEPARATOR,
            [
                ROOT,
                'Cabin',
                $nameSpace,
                'config',
            ]
        );
        if (!\file_exists($dir.'/content_security_policy.json')) {
            if (\file_put_contents($dir . '/content_security_policy.json', '{"inherit": true}') === false) {
                return false;
            }
        }
        if (!\file_exists($dir.'/gadgets.json')) {
            if (\file_put_contents($dir . '/gadgets.json', '[]') === false) {
                return false;
            }
        }
        if (!\file_exists($dir.'/motifs.json')) {
            if (\file_put_contents($dir . '/motifs.json', '[]') === false) {
                return false;
            }
        }
        if (!\file_exists($dir.'/twig_vars.json')) {
            if (\file_put_contents($dir . '/twig_vars.json', '[]') === false) {
                return false;
            }
        }
        return true;
    }

    /**
     * Create the initial symlinks for this Cabin
     *
     * @param string $nameSpace
     * @return bool
     */
    protected function createSymlinks(string $nameSpace): bool
    {
        $target = \implode(
            DIRECTORY_SEPARATOR,
            [
                ROOT,
                'Cabin',
                $nameSpace,
                'config',
            ]
        );
        $link = \implode(
            DIRECTORY_SEPARATOR,
            [
                ROOT,
                'config',
                'Cabin',
                $nameSpace
            ]
        );
        if (!\symlink($target, $link)) {
            return false;
        }
        return true;
    }

    /**
     * Create the default configuration
     *
     * @param string $nameSpace
     * @return bool
     */
    protected function defaultCabinConfig(string $nameSpace): bool
    {
        $twigEnv = \Airship\configWriter(ROOT . '/Cabin/' . $nameSpace . '/config/templates');
        return \file_put_contents(
            ROOT . '/Cabin/' . $nameSpace . '/config/config.json',
            $twigEnv->render('config.twig') // No arguments.
        ) !== false;
    }

    /**
     * Cabin install process.
     *
     * 1. Extract files to proper directory.
     * 2. Run the update triggers (install hooks and incremental upgrades)
     * 3. Create/update relevant configuration files.
     * 4. Create symbolic links.
     * 5. Clear the cache files.
     *
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
        $metadata = \Airship\parseJSON($updater->getMetadata(), true);

        // Overwrite files
        $updater->extractTo(ROOT . '/Cabin/' . $ns);

        // Run the update trigger.
        Sandbox::safeRequire('phar://' . $alias . '/update_trigger.php');

        // Free up the updater alias
        $garbageAlias = Base64UrlSafe::encode(\random_bytes(33)) . '.phar';
        $updater->setAlias($garbageAlias);
        unset($updater);

        return $this->configure($ns, $metadata);
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

    /**
     * Add the new cabin to config/cabins.json
     *
     * @param string $nameSpace
     * @param array $metadata
     * @return bool
     */
    protected function updateCabinsRegistry(string $nameSpace, array $metadata): bool
    {
        // Default route
        $defaultPath = $metadata['default_path']
            ?? '*/' . $this->supplier->getName() . '/' . $this->package;

        $twigEnv = \Airship\configWriter(ROOT . '/config/templates');
        $cabins = \Airship\loadJSON(ROOT . '/config/cabins.json');

        // We want to load everything before the wildcard entry:
        if (isset($cabins['*'])) {
            $newCabins = [];
            foreach (\array_keys($cabins) as $k) {
                if ($k !== '*') {
                    $newCabins[$k] = $cabins[$k];
                }
            }
            $newCabins[$defaultPath] = [
                'https' => false,
                'canon_url' => '/' . $this->supplier->getName() . '/' . $this->package,
                'language' => $metadata['lang'] ?? 'en-us',
                'name' => $nameSpace
            ];
            $newCabins['*'] = $cabins['*'];
        } else {
            $newCabins = $cabins;
            $newCabins[$defaultPath] = [
                'https' => false,
                'canon_url' => '/' . $this->supplier->getName() . '/' . $this->package,
                'language' => $metadata['lang'] ?? 'en-us',
                'name' => $nameSpace
            ];
        }
        return \file_put_contents(
            ROOT . '/config/cabins.json',
            $twigEnv->render('cabins.twig', ['cabins' => $newCabins])
        ) !== false;
    }
}
