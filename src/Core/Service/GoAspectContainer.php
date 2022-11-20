<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Core\Service;

use Go\Core\Instrument\Singleton;
use Go\Lang\Attribute\Aspect;
use InvalidArgumentException;
use ReflectionClass;

/**
 * Aspect container contains list of all pointcuts and advisors
 */
class GoAspectContainer
{
    use Singleton;

    /**
     * List of aspect class names
     *
     * @var class-string[]
     */
    protected static array $aspects = [];

    /**
     * Cached timestamp for aspects
     *
     * @var int
     */
    protected static int $maxTimestamp = 0;

    /**
     * Register GoAspectContainer
     *
     * @return void
     */
    public static function register(): void
    {
        $instance = self::getInstance();
        $instance->setInitialized();
    }

    /**
     * Register an aspect in the container
     *
     * @param string $aspect Aspect class name
     * @return void
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public static function registerAspect(string $aspect): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $refAspect = new ReflectionClass($aspect);
        $attributes = $refAspect->getAttributes(Aspect::class);
        if (empty($attributes)) {
            throw new InvalidArgumentException(
                "Aspect \"{$refAspect->getName()}\" should be annotated with \"#[\Go\Lang\Attribute\Aspect]\""
            );
        }

        self::getInitializedInstance();
        $aspectName = $refAspect->getName();
        self::$aspects[$aspectName] = $aspectName;
    }

    /**
     * Returns list of registered aspects
     *
     * @return class-string[]
     */
    public static function getAspects(): array
    {
        return self::$aspects;
    }

    /**
     * Checks the freshness of AOP cache
     *
     * @param int $timestamp
     * @return bool Whether the cache is fresh
     */
    public static function isFresh(int $timestamp): bool
    {
        if (!self::$maxTimestamp && !empty(self::$aspects)) {
            self::$maxTimestamp = max(array_map('filemtime', self::$aspects));
        }

        return self::$maxTimestamp <= $timestamp;
    }
}
