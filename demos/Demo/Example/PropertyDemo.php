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

use RuntimeException;

/**
 * Example class to show how to intercept an access to the properties
 */
class PropertyDemo
{
    public int $publicProperty = 123;

    protected int $protectedProperty = 456;

    private string $privateProperty = 'test';

    protected array $indirectModificationCheck = [4, 5, 6];

    public function showProtected(): void
    {
        echo $this->protectedProperty, PHP_EOL;
    }

    public function setProtected(int $newValue): void
    {
        $this->protectedProperty = $newValue;
    }

    public function showPrivate(): void
    {
        echo $this->privateProperty;
    }

    public function __construct()
    {
        array_push($this->indirectModificationCheck, 7, 8, 9);
        if (count($this->indirectModificationCheck) !== 6) {
            throw new RuntimeException("Indirect modification doesn't work!");
        }
        $this->privateProperty = $this->privateProperty . 'bar';
    }
}
