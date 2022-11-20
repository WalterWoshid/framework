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
 *
 * @noinspection PhpDocFieldTypeMismatchInspection
 * @noinspection PhpDocSignatureInspection
 */
namespace Go\Core\Instrument;

use Go\Core\Exception\NotInitialized;

trait Singleton
{
    /**
     * Protected constructor is used to prevent direct creation
     */
    final protected function __construct() {}

    /**
     * Flag to determine if the instance is created
     *
     * @var bool
     */
    protected bool $initialized = false;

    /**
     * Instance of the class
     *
     * @var ?self
     */
    protected static ?self $instance = null;

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (!static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Get initialized instance
     *
     * @return self
     */
    private static function getInitializedInstance(): self
    {
        $instance = static::getInstance();
        if (!$instance->initialized) {
            throw new NotInitialized;
        }

        return $instance;
    }

    /**
     * Check if the instance is initialized
     *
     * @return bool
     */
    public static function isInitialized(): bool
    {
        return static::getInstance()->initialized;
    }

    /**
     * Set the instance as initialized
     *
     * @return void
     */
    public function setInitialized(): void
    {
        static::getInstance()->initialized = true;
    }
}
