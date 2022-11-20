<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2019-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Aop\Framework;

use Error;
use Exception;
use Go\Core\AspectContainer;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

class ReflectionConstructorInvocationTest extends AbstractInterceptorTest
{
    public function testCanCreateObjectDuringInvocation(): void
    {
        $invocation = new ReflectionConstructorInvocation([], Exception::class);
        $result     = $invocation->__invoke();
        $this->assertInstanceOf(Exception::class, $result);
    }

    public function testKnowsAboutSpecialClassSuffix(): void
    {
        $specialName = Exception::class . AspectContainer::AOP_PROXIED_SUFFIX;
        $invocation  = new ReflectionConstructorInvocation([], $specialName);
        $result      = $invocation->__invoke();
        $this->assertInstanceOf(Exception::class, $result);
    }

    public function testCanExecuteAdvicesDuringConstruct(): void
    {
        $sequence   = [];
        $advice     = $this->getAdvice($sequence);
        $before     = new BeforeInterceptor($advice);
        $invocation = new ReflectionConstructorInvocation([$before], Exception::class);
        $this->assertEmpty($sequence);
        $invocation->__invoke(['Message', 100]);
        $this->assertContains('advice', $sequence);
    }

    public function testStringRepresentation(): void
    {
        $invocation = new ReflectionConstructorInvocation([], Exception::class);
        $name       = (string)$invocation;

        $this->assertEquals('initialization(Exception)', $name);
    }

    public function testReturnsConstructor(): void
    {
        $invocation = new ReflectionConstructorInvocation([], Exception::class);
        $ctor       = $invocation->getConstructor();
        $this->assertInstanceOf(ReflectionMethod::class, $ctor);
        $this->assertEquals('__construct', $ctor->name);
    }

    public function testReturnsThis(): void
    {
        $invocation = new ReflectionConstructorInvocation([], Exception::class);
        $instance   = $invocation->getThis();
        $this->assertNull($instance);
        $object = $invocation->__invoke(['Some error', 100]);
        $this->assertEquals($object, $invocation->getThis());
    }

    /**
     * @throws ReflectionException
     */
    public function testCanCreateAnInstanceEvenWithNonPublicConstructor(): void
    {
        $loadedClass = null;
        try {
            $testClassInstance = new class('Test') {
                public string $message;

                private function __construct(string $message)
                {
                    $this->message = $message;
                }
            };
            $loadedClass = get_class($testClassInstance);
        } catch (Error) {
            // let's look for all class names to find our anonymous one
            foreach (get_declared_classes() as $loadedClass) {
                $refClass = new ReflectionClass($loadedClass);
                if ($refClass->getFileName() === __FILE__ && str_contains($refClass->getName(), 'anonymous')) {
                    // loadedClass will contain our anonymous class
                    break;
                }
            }
        }
        $testClassName = $loadedClass;
        $invocation    = new ReflectionConstructorInvocation([], $testClassName);
        $result        = $invocation->__invoke(['Hello']);
        $this->assertInstanceOf($testClassName, $result);
        $this->assertSame('Hello', $result->message);
    }
}
