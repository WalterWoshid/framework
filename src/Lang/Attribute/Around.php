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
 * # AfterThrowing advice attribute
 *
 * @todo
 */
#[Attribute(
    Attribute::TARGET_METHOD |
    Attribute::TARGET_PROPERTY
)]
class Around implements BaseInterceptor
{
    /**
     * Around constructor
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
