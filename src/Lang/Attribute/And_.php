<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * @author Valentin Wotschel <wotschel.valentin@googlemail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Lang\Attribute;

use Attribute;

/**
 * # "And" control-flow attribute
 *
 * @todo
 */
#[Attribute(
    Attribute::IS_REPEATABLE |
    Attribute::TARGET_METHOD |
    Attribute::TARGET_PROPERTY
)]
class And_ implements BaseControlFlow
{
}
