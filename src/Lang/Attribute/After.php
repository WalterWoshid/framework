<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Lang\Attribute;

use Attribute;

/**
 * # After advice attribute
 *
 * Use in combination with {@link \Go\Lang\Attribute\Execution Execution}
 *
 * <br>
 * Example:
 * ```php
 * use Go\Aop\Intercept\MethodInvocation;
 * use Go\Lang\Attribute\After;
 * use Go\Lang\Attribute\Aspect;
 * use Go\Lang\Attribute\Execution;
 *
 * #[Aspect]
 * class MyAspect
 * {
 *     #[After]
 *     #[Execution(class: 'MyClass', method: 'myMethod')]
 *     public function afterMethodExecution(MethodInvocation $invocation)
 *     {
 *         // Do something after method execution
 *     }
 * }
 * ```
 */
#[Attribute(
    Attribute::TARGET_METHOD |
    Attribute::TARGET_PROPERTY
)]
class After implements BaseInterceptor
{
    /**
     * After constructor
     *
     * @param string|null $pointcut
     */
    public function __construct(
        private readonly ?string $pointcut = null,
    ) {}

    /**
     * Get pointcut name
     *
     * @return string|null
     */
    public function getPointcut(): ?string
    {
        return $this->pointcut;
    }
}
