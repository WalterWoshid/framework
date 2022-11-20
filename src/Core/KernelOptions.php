<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * @author Valentin Wotschel <wotschel.valentin@googlemail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Core;

use Go\Aop\Feature;
use Go\Core\Instrument\Singleton;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

use function in_array;

/**
 * Options for kernel
 */
class KernelOptions
{
    use Singleton;

    /**
     * Prefix for function interceptor
     */
    public const FUNCTION_PREFIX = 'func';

    /**
     * Prefix for properties interceptor
     */
    public const PROPERTY_PREFIX = 'prop';

    /**
     * Prefix for method interceptor
     */
    public const METHOD_PREFIX = 'method';

    /**
     * Prefix for static method interceptor
     */
    public const STATIC_METHOD_PREFIX = 'static';

    /**
     * Trait introduction prefix
     */
    public const INTRODUCTION_TRAIT_PREFIX = 'trait';

    /**
     * Interface introduction prefix
     */
    public const INTRODUCTION_INTERFACE_PREFIX = 'interface';

    /**
     * Initialization prefix is used for initialization pointcuts
     */
    public const INIT_PREFIX = 'init';

    /**
     * Initialization prefix is used for initialization pointcuts
     */
    public const STATIC_INIT_PREFIX = 'staticinit';

    /**
     * Suffix that will be added to all proxied class names
     */
    public const AOP_PROXIED_SUFFIX = '__AopProxied';

    /**
     * Kernel in debug mode
     *
     * @var bool|null
     */
    private ?bool $debug = null;

    /**
     * Application root directory
     *
     * @var string|null
     */
    public ?string $appDir = null;

    /**
     * Cache directory where all compiled classes will be stored
     *
     * @var string|null
     */
    private ?string $cacheDir = null;

    /**
     * List of features to enable
     *
     * @var Feature[]
     */
    private array $features = [];

    /**
     * List of paths to include
     *
     * @var string[]
     */
    private array $includePaths = [];

    /**
     * List of paths to exclude
     *
     * @var string[]
     */
    private array $excludePaths = [];

    /**
     * Cache adapter for storing compiled classes
     *
     * @var ?TagAwareAdapter
     */
    public ?TagAwareAdapter $adapter = null;

    /**
     * # Set options
     *
     * @param bool            $debug        Debug mode
     * @param string          $appDir       Path to the application directory
     * @param string          $cacheDir     Path to the cache directory
     * @param TagAwareAdapter $adapter      Cache adapter for storing compiled classes
     * @param Feature[]       $features     List of features to enable
     * @param string[]        $includePaths List of paths to include
     * @param string[]        $excludePaths List of paths to exclude
     *
     * @return void
     */
    public static function setOptions(
        bool            $debug,
        string          $appDir,
        string          $cacheDir,
        TagAwareAdapter $adapter,
        array           $features,
        array           $includePaths,
        array           $excludePaths,
    ): void {
        $instance = self::getInstance();

        $instance->debug        = $debug;
        $instance->appDir       = $appDir;
        $instance->cacheDir     = $cacheDir;
        $instance->adapter      = $adapter;
        $instance->features     = $features;
        $instance->includePaths = $includePaths;
        $instance->excludePaths = $excludePaths;

        $instance->setInitialized();
    }

    /**
     * Check if kernel is in debug mode
     *
     * @return bool
     */
    public static function isDebug(): bool
    {
        static $debug;
        if (!$debug) {
            $instance = self::getInitializedInstance();
            $debug = $instance->debug;
        }

        return $debug;
    }

    /**
     * Get application root directory
     *
     * @return string
     */
    public static function getAppDir(): string
    {
        static $appDir;
        if (!$appDir) {
            $instance = self::getInitializedInstance();
            $appDir = $instance->appDir;
        }

        return $appDir;
    }

    /**
     * Get cache directory
     *
     * @return string
     */
    public static function getCacheDir(): string
    {
        static $cacheDir;
        if (!$cacheDir) {
            $instance = self::getInitializedInstance();
            $cacheDir = $instance->cacheDir;
        }

        return $cacheDir;
    }

    /**
     * Get compiled cache directory where all compiled classes will be stored
     *
     * @return string
     */
    public static function getCompiledCacheDir(): string
    {
        static $compiledCacheDir;
        if (!$compiledCacheDir) {
            $instance = self::getInitializedInstance();
            $compiledCacheDir = $instance->cacheDir . '/compiled';
        }

        return $compiledCacheDir;
    }

    /**
     * Get the cache adapter
     *
     * @return TagAwareAdapter
     */
    public static function getCacheAdapter(): TagAwareAdapter
    {
        static $cacheAdapter;
        if (!$cacheAdapter) {
            $instance = self::getInitializedInstance();
            $cacheAdapter = $instance->adapter;
        }

        return $cacheAdapter;
    }

    /**
     * Check if kernel has a specific feature enabled
     *
     * @param Feature $feature
     *
     * @return bool
     */
    public static function hasFeature(Feature $feature): bool
    {
        $instance = self::getInitializedInstance();

        if (in_array(Feature::NONE, $instance->features, true)) {
            return false;
        }

        return in_array($feature, $instance->features, true);
    }

    /**
     * Get list of included paths
     *
     * @return string[]
     */
    public static function getIncludePaths(): array
    {
        static $includePaths;
        if (!$includePaths) {
            $instance = self::getInitializedInstance();
            $includePaths = $instance->includePaths;
        }

        return $includePaths;
    }

    /**
     * Get list of excluded paths
     *
     * @return string[]
     */
    public static function getExcludePaths(): array
    {
        static $excludePaths;
        if (!$excludePaths) {
            $instance = self::getInitializedInstance();
            $excludePaths = $instance->excludePaths;
        }

        return $excludePaths;
    }
}
