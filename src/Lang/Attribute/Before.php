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
 * # Before advice attribute
 *
 * Use in combination with {@link \Go\Lang\Attribute\Execution Execution}
 *
 * <br>
 * Example:
 * ```php
 * use Go\Aop\Intercept\MethodInvocation;
 * use Go\Lang\Attribute\Aspect;
 * use Go\Lang\Attribute\Before;
 * use Go\Lang\Attribute\Execution;
 *
 * class MyAspect
 * {
 *     #[Before]
 *     #[Execution(class: 'MyClass', method: 'myMethod')]
 *     public function beforeMethodExecution(MethodInvocation $invocation)
 *     {
 *         // Do something before method execution
 *     }
 * }
 * ```
 */
#[Attribute(
    Attribute::TARGET_METHOD |
    Attribute::TARGET_PROPERTY
)]
class Before implements BaseInterceptor
{
    /**
     * Before constructor
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
