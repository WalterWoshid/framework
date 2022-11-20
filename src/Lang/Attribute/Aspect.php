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
 * # Aspect attribute
 *
 * Aspects should be marked with this attribute to be registered in the container
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Aspect implements BaseAttribute
{
}
