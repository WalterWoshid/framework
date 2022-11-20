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
 * # Declare parents attribute
 *
 * Use in combination with {@link \Go\Lang\Attribute\Execution Execution}
 *
 * <br>
 * Example:
 * todo
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class DeclareParents implements BaseAttribute
{
    private array $interfaces = [];
    private array $traits = [];

    /**
     * DeclareParents constructor
     *
     * @param string|array|null $interface
     * @param string|array|null $trait
     */
    public function __construct(
        string|array|null $interface = null,
        string|array|null $trait     = null,
    ) {
        if (is_string($interface)) {
            $this->interfaces[] = $interface;
        } elseif (is_array($interface)) {
            $this->interfaces = $interface;
        }

        if (is_string($trait)) {
            $this->traits[] = $trait;
        } elseif (is_array($trait)) {
            $this->traits = $trait;
        }
    }

    /**
     * Get interfaces
     *
     * @return string[]
     */
    public function getInterfaces(): array
    {
        return $this->interfaces;
    }

    /**
     * Get traits
     *
     * @return string[]
     */
    public function getTraits(): array
    {
        return $this->traits;
    }
}
