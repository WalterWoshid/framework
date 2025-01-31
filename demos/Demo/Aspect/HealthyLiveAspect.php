<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Demo\Aspect;

use Demo\Example\HumanDemo;
use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Annotation\After;
use Go\Lang\Annotation\Before;
use Go\Lang\Annotation\Pointcut;

/**
 * Healthy live aspect
 */
class HealthyLiveAspect implements Aspect
{
    /**
     * Pointcut for eat method
     *
     * @Pointcut("execution(public Demo\Example\HumanDemo->eat(*))")
     */
    protected function humanEat(): void
    {
    }

    /**
     * Washing hands before eating
     *
     * @Before("$this->humanEat")
     */
    protected function washUpBeforeEat(MethodInvocation $invocation): void
    {
        /** @var $person HumanDemo */
        $person = $invocation->getThis();
        $person->washUp();
    }

    /**
     * Method that advices to clean the teeth after eating
     *
     * @After("$this->humanEat")
     */
    protected function cleanTeethAfterEat(MethodInvocation $invocation): void
    {
        /** @var $person HumanDemo */
        $person = $invocation->getThis();
        $person->cleanTeeth();
    }

    /**
     * Method that advice to clean the teeth before going to sleep
     *
     * @Before("execution(public Demo\Example\HumanDemo->sleep(*))")
     */
    protected function cleanTeethBeforeSleep(MethodInvocation $invocation): void
    {
        /** @var $person HumanDemo */
        $person = $invocation->getThis();
        $person->cleanTeeth();
    }
}
