<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Core\Loader;

use Go\Aop\Advisor;
use Go\Aop\Framework\DeclareErrorInterceptor;
use Go\Aop\Framework\TraitIntroductionInfo;
use Go\Aop\Pointcut;
use Go\Aop\Support\DeclareParentsAdvisor;
use Go\Aop\Support\DefaultPointcutAdvisor;
use ReflectionClass;
use UnexpectedValueException;

/**
 * Introduction aspect extension
 */
class IntroductionAspectExtension extends AbstractAspectLoaderExtension
{
    /**
     * Loads definition from specific point of aspect into the container
     *
     * @param object          $aspect           Class instance with aspect annotation
     * @param ReflectionClass $reflectionAspect Reflection of point
     *
     * @return array<string,Pointcut>|array<string,Advisor>
     *
     * @throws UnexpectedValueException
     */
    public function load(object $aspect, ReflectionClass $reflectionAspect): array
    {
        $loadedItems = [];
        foreach ($reflectionAspect->getProperties() as $aspectProperty) {
            $propertyId  = $reflectionAspect->getName() . '->'. $aspectProperty->getName();
            // $annotations = $this->arrayAdapter->getPropertyAnnotations($aspectProperty);
            $annotations = []; // todo

            foreach ($annotations as $annotation) {
                if ($annotation instanceof DeclareParents) {
                    $pointcut = $this->parsePointcut($aspect, $aspectProperty, $annotation->value);

                    $interfaces       = $annotation->getInterfaces();
                    $traits           = $annotation->getTraits();
                    $introductionInfo = new TraitIntroductionInfo($traits, $interfaces);
                    $advisor          = new DeclareParentsAdvisor($pointcut, $introductionInfo);

                    $loadedItems[$propertyId] = $advisor;
                } elseif ($annotation instanceof Annotation\DeclareError) {
                    $pointcut = $this->parsePointcut($aspect, $reflectionAspect, $annotation->value);

                    $aspectProperty->setAccessible(true);
                    $errorMessage     = $aspectProperty->getValue($aspect);
                    $errorLevel       = $annotation->level;
                    $introductionInfo = new DeclareErrorInterceptor($errorMessage, $errorLevel, $annotation->value);
                    $loadedItems[$propertyId] = new DefaultPointcutAdvisor($pointcut, $introductionInfo);
                    break;

                } else {
                    throw new UnexpectedValueException('Unsupported annotation class: ' . get_class($annotation));
                }
            }
        }

        return $loadedItems;
    }
}
