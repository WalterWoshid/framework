<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Core\Exception;

use RuntimeException;

class AbstractAdapterNotImplementedException extends RuntimeException
{
    /**
     * AbstractAdapterNotImplementedException constructor
     *
     * @param string $adapterClass
     */
    public function __construct(string $adapterClass)
    {
        $message = "Adapter $adapterClass is not implemented yet";
        parent::__construct($message, 1);
    }
}
