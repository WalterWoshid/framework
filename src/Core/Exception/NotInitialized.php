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

class NotInitialized extends RuntimeException
{
    /**
     * NotInitialized constructor
     */
    public function __construct()
    {
        $className = $this->getTrace()[0]['class'];
        parent::__construct("$className has not been initialized yet", 1);
    }
}
