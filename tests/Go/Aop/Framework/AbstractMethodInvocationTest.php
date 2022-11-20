<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2011-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Aop\Framework;

use Go\Aop\Support\AnnotationAccess;
use PHPUnit\Framework\TestCase;

class AbstractMethodInvocationTest extends TestCase
{
    protected AbstractMethodInvocation $invocation;

    public function setUp(): void
    {
        $this->invocation = $this->getMockForAbstractClass(
            AbstractMethodInvocation::class,
            [[], self::class, __FUNCTION__]
        );
    }

    public function testInvocationReturnsMethod(): void
    {
        $this->assertEquals(self::class, $this->invocation->getMethod()->class);
        $this->assertEquals('setUp', $this->invocation->getMethod()->name);
    }

    public function testProvidesAccessToAnnotations(): void
    {
        $this->assertInstanceOf(AnnotationAccess::class, $this->invocation->getMethod());
    }
}
