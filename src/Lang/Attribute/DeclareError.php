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
use JetBrains\PhpStorm\ExpectedValues;

/**
 * # DeclareError attribute
 *
 * Use in combination with {@link \Go\Lang\Attribute\Execution Execution} or
 * {@link \Go\Lang\Attribute\Within Within}
 *
 * <br>
 * Example:
 *
 * ```php
 * use Go\Aop\Aspect;
 * use Go\Lang\Attribute\DeclareError;
 * use Go\Lang\Attribute\ErrorType;
 * use Go\Lang\Attribute\Execution;
 *
 * class MyAspect implements Aspect
 * {
 *     #[DeclareError(ErrorType::DEPRECATED)]
 *     #[Execution(class: 'MyClass', method: 'myMethod')]
 *     protected string $errorMessage = 'This method is deprecated';
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class DeclareError implements BaseAttribute
{
    const WARNING = 0;
    const DEPRECATED = 1;

    /**
     * DeclareError constructor
     *
     * @param int $errorType Type of error (see constants)<br>
     *                       DeclareError::WARNING | DeclareError::DEPRECATED
     */
    public function __construct(
        #[ExpectedValues(flagsFromClass: self::class)]
        private readonly int $errorType,
    ) {}
}
