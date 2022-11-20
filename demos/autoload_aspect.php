<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

use Demo\Aspect\AwesomeAspectKernel;
use Go\Aop\Feature;

include __DIR__ . '/../vendor/autoload.php';

// Initialize demo aspect container
AwesomeAspectKernel::init(
    debug: true,
    appDir: __DIR__ . '/../demos',
    cacheDir: __DIR__ . '/cache',

    features: Feature::INTERCEPT_FUNCTIONS,
);
