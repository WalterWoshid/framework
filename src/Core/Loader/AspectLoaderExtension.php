<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Core\Loader;

use Go\Aop\Advisor;
use Go\Aop\Pointcut;
use ReflectionClass;

/**
 * Extension interface that defines an API for aspect loaders
 */
interface AspectLoaderExtension
{
    /**
     * Loads definition from specific point of aspect into the container
     *
     * @param object          $aspect           Class with aspect annotation
     * @param ReflectionClass $reflectionAspect Reflection of aspect
     *
     * @return array<string,Pointcut>|array<string,Advisor>
     */
    public function load(object $aspect, ReflectionClass $reflectionAspect): array;
}
