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

use Go\Aop\Advice;
use Go\Aop\Advisor;
use Go\Core\Instrument\Singleton;
use InvalidArgumentException;

/**
 * Provides an interface for loading of advisors from the container
 */
class LazyAdvisorAccessor
{
    use Singleton;

    /**
     * Register LazyAdvisorAccessor
     *
     * @return void
     */
    public static function register(): void
    {
        $instance = self::getInstance();
        $instance->setInitialized();
    }

    /**
     * Magic advice accessor
     *
     * @throws InvalidArgumentException if referenced value is not an advisor
     */
    public function __get(string $name): Advice
    {
        if ($this->container->has($name)) {
            $advisor = $this->container->get($name);
        } else {
            list(, $advisorName) = explode('.', $name);
            list($aspect)        = explode('->', $advisorName);
            $aspectInstance      = $this->container->getAspect($aspect);
            $this->loader->loadAndRegister($aspectInstance);

            $advisor = $this->container->get($name);
        }

        if (!$advisor instanceof Advisor) {
            throw new InvalidArgumentException("Reference {$name} is not an advisor");
        }
        $this->$name = $advisor->getAdvice();

        return $this->$name;
    }
}
