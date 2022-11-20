<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2013-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Aop\Framework;

use Go\Aop\IntroductionInfo;

/**
 * Advice for introduction that holds list of traits and interfaces for the concrete class
 */
class TraitIntroductionInfo implements IntroductionInfo
{
    /**
     * Introduced interface
     *
     * @var string[]
     */
    private array $interfaces;

    /**
     * Introduced trait
     *
     * @var string[]
     */
    private array $traits;

    /**
     * Creates a TraitIntroductionInfo with given trait name and interface name.
     */
    public function __construct(array $traits, array $interfaces)
    {
        $this->traits      = $traits;
        $this->interfaces = $interfaces;
    }

    /**
     * Returns the additional interface introduced by this Advisor or Advice.
     *
     * @return string[]
     */
    public function getInterfaces(): array
    {
        return $this->interfaces;
    }

    /**
     * Returns the additional trait with realization of introduced interface
     *
     * @return string[]
     */
    public function getTraits(): array
    {
        return $this->traits;
    }
}
