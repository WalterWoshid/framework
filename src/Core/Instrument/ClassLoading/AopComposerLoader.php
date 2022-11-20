<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2013-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Core\Instrument\ClassLoading;

use Go\Core\Instrument\PathResolver;
use Go\Core\Instrument\Transformer\FilterInjectorTransformer;
use Go\Core\KernelOptions;
use SplFileInfo;
use Go\Core\Instrument\FileSystem\Enumerator;
use Composer\Autoload\ClassLoader;

use function is_array;
use function spl_autoload_functions;
use function spl_autoload_register;
use function spl_autoload_unregister;

/**
 * AopComposerLoader class is responsible to use a weaver for classes instead of original one
 */
class AopComposerLoader
{
    /**
     * File enumerator
     */
    protected Enumerator $fileEnumerator;

    /**
     * Cache state
     */
    private array $cacheState;

    /**
     * Was initialization successful or not
     */
    private static bool $wasInitialized = false;

    /**
     * Constructs a wrapper for the composer loader
     *
     * @param ClassLoader $original Original autoloader
     *
     * @return void
     */
    public function __construct(protected ClassLoader $original)
    {
        $prefixes     = $original->getPrefixes();
        $appDir       = KernelOptions::getAppDir();
        $includePaths = KernelOptions::getIncludePaths();
        $excludePaths = KernelOptions::getExcludePaths();

        if (!empty($prefixes)) {
            // Let's exclude core dependencies from that list
            if (isset($prefixes['Dissect'])) {
                $excludePaths[] = $prefixes['Dissect'][0];
            }
        }

        $this->fileEnumerator = new Enumerator($appDir, $includePaths, $excludePaths);
        // $this->cacheState     = $container->get('aspect.cache.path.manager')->queryCacheState();
        // todo: check if this is needed
        $this->cacheState     = [];
    }

    /**
     * Initialize aspect autoloader and returns status whether initialization was successful or not
     *
     * Replaces original composer autoloader with wrapper
     *
     * @return bool
     */
    public static function init(): bool
    {
        $loaders = spl_autoload_functions();

        foreach ($loaders as &$loader) {
            $loaderToUnregister = $loader;
            if (is_array($loader) && ($loader[0] instanceof ClassLoader)) {
                $classLoader = &$loader[0];
                $classLoader = new AopComposerLoader($classLoader);
                self::$wasInitialized = true;
            }
            spl_autoload_unregister($loaderToUnregister);
        }
        unset($loader);

        foreach ($loaders as $loader) {
            spl_autoload_register($loader);
        }

        return self::$wasInitialized;
    }

    /**
     * Autoload a class by its name
     *
     * @param string $class Class name to load
     */
    public function loadClass(string $class): void
    {
        $file = $this->findFile($class);

        if ($file !== false) {
            include $file;
        }
    }

    /**
     * Finds either the path to the file where the class is defined,
     * or gets the appropriate php://filter stream for the given class
     *
     * @return string|false The path/resource if found, false otherwise
     */
    public function findFile(string $class): bool|string
    {
        static $isAllowedFilter = null, $isProduction = false;
        if (!$isAllowedFilter) {
            $isAllowedFilter = $this->fileEnumerator->getFilter();
            $isProduction    = KernelOptions::isDebug();
        }

        $file = $this->original->findFile($class);

        if ($file !== false) {
            $file = PathResolver::realpath($file) ?: $file;
            $cacheState = $this->cacheState[$file] ?? null;
            if ($cacheState && $isProduction) {
                $file = $cacheState['cacheUri'] ?: $file;
            } elseif ($isAllowedFilter(new SplFileInfo($file))) {
                // Can be optimized here with $cacheState even for debug mode, but no needed right now
                $file = FilterInjectorTransformer::rewrite($file);
            }
        }

        return $file;
    }

    /**
     * Whether loader was initialized
     */
    public static function wasInitialized(): bool
    {
        return self::$wasInitialized;
    }
}
