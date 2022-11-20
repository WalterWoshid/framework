<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2014-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Aop;

/**
 * Enumeration of framework features to use in checking and configuration
 */
interface Feature
{
    /**
     * No features enabled
     */
    public const NONE = 0;

    /**
     * Enables interception of system functions.
     * By default, this feature is disabled, because this option is very expensive
     */
    public const INTERCEPT_FUNCTIONS = 1;

    /**
     * Enables interception of "new" operator in the source code.
     * By default, this feature is disabled, because it's very tricky
     */
    public const INTERCEPT_INITIALIZATIONS = 2;

    /**
     * Enables interception of "include" / "require" operations in legacy code.
     * By default, this feature is disabled, because only composer should be used
     */
    public const INTERCEPT_INCLUDES = 4;

    /**
     * Do not check the cache presence and assume that cache is already prepared.
     *
     * This flag is usable for read-only file systems (GAE, phar, etc)
     */
    public const PREBUILT_CACHE = 64;

    /**
     * Enables usage of parameter widening for PHP>=7.2.0
     *
     * @see https://wiki.php.net/rfc/parameter-no-type-variance
     */
    public const PARAMETER_WIDENING = 128;
}
