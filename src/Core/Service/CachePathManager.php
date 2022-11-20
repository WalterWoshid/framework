<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2014-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Core\Service;

use Go\Core\Instrument\Singleton;
use Go\Core\KernelOptions;
use JetBrains\PhpStorm\ArrayShape;

class CachePathManager
{
    use Singleton;

    /**
     * Cached metadata for transformation state for the concrete file
     *
     * @var array{ cacheUri: string, filemtime: int }
     */
    #[ArrayShape([
        'cacheUri'  => 'string',
        'filemtime' => 'int',
    ])]
    protected static array $cacheState = [];

    /**
     * New metadata items, that were not present in $cacheState
     *
     * @var array{ cacheUri: string, filemtime: int }
     */
    #[ArrayShape([
        'cacheUri'  => 'string',
        'filemtime' => 'int',
    ])]
    protected static array $newCacheState = [];

    /**
     * Register CachePathManager
     *
     * @return void
     */
    public static function register(): void
    {
        $instance = self::getInstance();
        $instance->setInitialized();
    }

    /**
     * Returns cache path for the requested file path
     *
     * @param string $resource
     * @return string
     */
    public static function getCachePathForResource(string $resource): string
    {
        return str_replace(KernelOptions::getAppDir(), KernelOptions::getCacheDir(), $resource);
    }

    /**
     * Returns compiled cache path for the requested file path
     *
     * @param string $resource
     * @return string
     */
    public static function getCompiledCachePathForResource(string $resource): string
    {
        return str_replace(
            KernelOptions::getAppDir(),
            KernelOptions::getCompiledCacheDir(),
            $resource
        );
    }

    /**
     * Tries to return information for queried resource
     *
     * @param string|null $resource Name of the file or null to get all cache state
     *
     * @return array{ cacheUri: string, filemtime: int }|null
     */
    #[ArrayShape([
        'cacheUri'  => 'string',
        'filemtime' => 'int',
    ])]
    public static function queryCacheState(string $resource = null): ?array
    {
        if (!$resource) {
            return self::$cacheState;
        }

        if (isset(self::$newCacheState[$resource])) {
            return self::$newCacheState[$resource];
        }

        if (isset(self::$cacheState[$resource])) {
            return self::$cacheState[$resource];
        }

        return null;
    }

    /**
     * Put a record about some resource in the cache
     *
     * This data will be persisted during object destruction
     *
     * @param string  $resource
     * @param string  $filemtime
     * @param ?string $cacheUri
     *
     * @return void
     */
    public static function setCacheState(
        string  $resource,
        string  $filemtime,
        ?string $cacheUri,
    ): void {
        self::$newCacheState[$resource] = [
            'cacheUri'  => $cacheUri,
            'filemtime' => $filemtime,
        ];
    }
}
