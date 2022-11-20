<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Core;

use Go\Aop\Feature;
use Go\Core\Exception\AbstractAdapterNotImplementedException;
use Go\Core\Instrument\ClassLoading\{AopComposerLoader, SourceTransformingLoader};
use Go\Core\Instrument\PathResolver;
use Go\Core\Instrument\Singleton;
use Go\Core\Service\{AdviceMatcher, AspectLoader, CachePathManager, GoAspectContainer, LazyAdvisorAccessor};
use InvalidArgumentException;
use JetBrains\PhpStorm\ExpectedValues;
use RuntimeException;
use Symfony\Component\Cache\Adapter\{AbstractAdapter, FilesystemAdapter, TagAwareAdapter};

/**
 * Abstract aspect kernel is used to prepare an application to work with aspects
 */
abstract class AspectKernel
{
    use Singleton;

    /**
     * # The application's aspects.
     *
     * These aspects will be applied to the application.
     *
     * Aspects should be marked with the {@link \Go\Lang\Attribute\Aspect #[Aspect]} attribute.
     *
     * @var array<int, class-string|string>
     */
    protected array $aspects = [];

    /**
     * # Init the kernel and make adjustments
     *
     * @param bool                   $debug        Whether kernel is in debug mode
     * @param string                 $appDir       Path to the application directory
     * @param string|TagAwareAdapter $cacheAdapter Symfony cache contract adapter.<br>
     *                               {@link https://symfony.com/doc/current/components/cache.html#cache-contracts}
     * @param ?string                $cacheDir     Path to the cache directory where all compiled classes will be stored
     * @param int-mask-of<Feature>   $features     List of features to enable as a bitmask
     *                                             Example: Feature::INTERCEPT_FUNCTIONS | Feature::INTERCEPT_METHODS
     * @param string[]               $includePaths Whitelist of directories where aspects should be applied.<br>
     *                                             If empty, then all directories will be scanned
     * @param string[]               $excludePaths Blacklist of directories where aspects should not be applied
     */
    public static function init(
        bool   $debug       = false,
        string $appDir      = __DIR__ . '/../../../../../',
        string|TagAwareAdapter $cacheAdapter = FilesystemAdapter::class,
        string $cacheDir    = null,
        #[ExpectedValues(flagsFromClass: Feature::class)]
        int   $features     = Feature::NONE,
        array $includePaths = [],
        array $excludePaths = [],
    ): void {
        // Check if kernel was already initialized
        if (KernelOptions::isInitialized()) {
            throw new RuntimeException('Aspect Kernel was already initialized');
        }

        $instance = static::getInstance();

        // Validate cache directory
        if ($cacheDir) {
            PathResolver::realpath($cacheDir);
        } else {
            throw new InvalidArgumentException('Cache directory is required');
        }

        switch (true) {
            // FilesystemAdapter
            case is_a($cacheAdapter, FilesystemAdapter::class, true):
                $adapter = $instance->createAdapter(FilesystemAdapter::class, $cacheDir);
                break;
            // @todo: ApcuAdapter
            // @todo: CouchbaseBucketAdapter
            // @todo: CouchbaseCollectionAdapter
            // @todo: DoctrineDbalAdapter
            // @todo: MemcachedAdapter
            // @todo: PdoAdapter
            // @todo: PhpFilesAdapter
            // @todo: Psr16Adapter
            // @todo: RedisAdapter

            // Adapter is not implemented
            case is_a($cacheAdapter, AbstractAdapter::class, true):
                throw new AbstractAdapterNotImplementedException($cacheAdapter);

            // Invalid adapter
            default:
                throw new InvalidArgumentException(
                    'Cache adapter should be a subclass of ' .
                    '\Symfony\Component\Cache\Adapter\AbstractAdapter::class'
                );
        }

        // Realpath for directories
        PathResolver::realpath($appDir);
        PathResolver::realpath($includePaths);
        PathResolver::realpath($excludePaths);
        $corePath = __DIR__ . '/../../';
        $excludePaths[] = PathResolver::realpath($corePath);
        $excludePaths[] = $cacheDir;

        // Convert features to array
        $features = is_array($features) ? $features : [$features];

        // Set options
        KernelOptions::setOptions(
            debug:        $debug,
            appDir:       $appDir,
            cacheDir:     $cacheDir,
            adapter:      $adapter,
            features:     $features,
            includePaths: $includePaths,
            excludePaths: $excludePaths,
        );

        // Set constants
        define('AOP_ROOT_DIR', $appDir);
        define('AOP_CACHE_DIR', $cacheDir);

        // Register all services
        AdviceMatcher::register();
        AspectLoader::register();
        CachePathManager::register();
        GoAspectContainer::register();
        LazyAdvisorAccessor::register();
        SourceTransformingLoader::register();

        // Register all aspects
        foreach ($instance->aspects as $aspect) {
            GoAspectContainer::registerAspect($aspect);
        }

        // Initialize composer loader
        AopComposerLoader::init();
    }

    /**
     * Creates FilesystemAdapter instance
     *
     * @param class-string $adapterClass
     * @param string $cacheDir
     *
     * @return TagAwareAdapter
     */
    private function createAdapter(
        string $adapterClass,
        string $cacheDir
    ): TagAwareAdapter {
        return match (true) {
            $adapterClass === FilesystemAdapter::class => new TagAwareAdapter(
                ...$this->createFilesystemAdapters($cacheDir)
            ),
        };
    }

    /**
     * Create FilesystemAdapters
     *
     * @param string $cacheDir
     * @return FilesystemAdapter[]
     */
    private function createFilesystemAdapters(string $cacheDir): array
    {
        return [
            new FilesystemAdapter('cache', 0, $cacheDir),
            new FilesystemAdapter('tags',  0, $cacheDir),
        ];
    }
}
