<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2018-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * @author Martin Fris <rasta@lj.sk>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Symfony\Component\Finder;

/**
 * This helper function overrides the PHP glob() function so it is able to be run with virtual file system,
 * which is supported by Webmozart\Glob\Glob
 *
 * @param      $pattern
 * @param null $flags
 *
 * @return string[]
 */
function glob($pattern, $flags = null) {
    return \Webmozart\Glob\Glob::glob($pattern, $flags);
}
