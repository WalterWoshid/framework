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

use Exception;
use RuntimeException;
use Throwable;

class AfterThrowingInterceptorTest extends AbstractInterceptorTest
{
    /**
     * @throws Throwable
     */
    public function testAdviceIsNotCalledAfterInvocation()
    {
        $sequence   = [];
        $advice     = $this->getAdvice($sequence);
        $invocation = $this->getInvocation($sequence);

        $interceptor = new AfterThrowingInterceptor($advice);
        $result = $interceptor->invoke($invocation);

        $this->assertEquals('invocation', $result, "Advice should not affect the return value of invocation");
        $this->assertEquals(['invocation'], $sequence, "Advice should not be invoked");
    }

    public function testAdviceIsCalledAfterExceptionInInvocation()
    {
        $sequence   = [];
        $advice     = $this->getAdvice($sequence);
        $invocation = $this->getInvocation($sequence, true);

        $interceptor = new AfterThrowingInterceptor($advice);
        $this->expectException(RuntimeException::class);
        try {
            $interceptor->invoke($invocation);
        } catch (Exception $e) {
            $this->assertEquals(['invocation', 'advice'], $sequence, "Advice should be invoked after invocation");
            throw $e;
        } catch (Throwable) {
        }
    }}
