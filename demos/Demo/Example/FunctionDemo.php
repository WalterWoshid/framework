<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Demo\Example;

/**
 * In this class we use system functions that will be intercepted by aspect
 */
class FunctionDemo
{
    /**
     * Some array transformer
     *
     * @param array $data Incoming array
     */
    public function testArrayFunctions(array $data = []): array
    {
        return array_flip(array_unique(array_values($data)));
    }

    /**
     * Outputs a file content
     */
    public function testFileContent(): void
    {
        echo '<pre>', htmlspecialchars(file_get_contents(__FILE__)), '</pre>';
    }
}
