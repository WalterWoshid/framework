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
 * # Pointcut attribute
 *
 * Meeting point for multiple advices
 *
 * <br>
 * Example:
 * ```php
 * use Go\Aop\Intercept\MethodInvocation;
 * use Go\Lang\Attribute\Aspect;
 * use Go\Lang\Attribute\Pointcut;
 *
 * #[Aspect]
 * class MyAspect
 * {
 *     #[Pointcut()]
 *     #[Execution(class: 'MyClass', method: 'myMethod')]
 *     public function myPointcut() {}
 *
 *     #[Around('myPointcut')]
 *     public function aroundMethodExecution(MethodInvocation $invocation)
 *
 *     #[Before('myPointcut')]
 *     public function beforeMethodExecution(MethodInvocation $invocation)
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Pointcut implements BaseAttribute
{
}
