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
use Go\Aop\Framework\AfterInterceptor;
use Go\Aop\Pointcut;
use Go\Core\Instrument\ReflectionHelper;
use Go\Lang\Attribute\{After, AfterThrowing, And_, Around, BaseAttribute, Before, Execution, Not_, Or_};
use JetBrains\PhpStorm\ExpectedValues;
use ReflectionAttribute;
use ReflectionClass;
use UnexpectedValueException;

/**
 * General aspect loader add common support for general advices, declared as attributes
 */
class GeneralAspectLoaderExtension extends AbstractAspectLoaderExtension
{
    /**
     * Loads definition from specific point of aspect into the container
     *
     * @param object          $aspect           Class with aspect attribute
     * @param ReflectionClass $reflectionAspect Reflection of point
     *
     * @return array<string,Pointcut>|array<string,Advisor>
     *
     * @throws UnexpectedValueException
     */
    public function load(object $aspect, ReflectionClass $reflectionAspect): array
    {
        $loadedItems = [];
        foreach ($reflectionAspect->getMethods() as $aspectMethod) {
            $attributes = $aspectMethod->getAttributes();

            // Ignore methods without attributes
            if (empty($attributes)) {
                continue;
            }

            // Create method id
            $methodId = $reflectionAspect->getName() . '->'. $aspectMethod->getName();

            // Collect all base attribute instances
            $baseAttributes = [];
            foreach ($attributes as $attribute) {
                $refAttribute = ReflectionHelper::createFromName($attribute->getName());
                if ($refAttribute->implementsInterface(BaseAttribute::class)) {
                    $baseAttributes[] = $attribute;
                }
            }

            // Handle attributes
            if (!empty($baseAttributes)) {
                $loadedItems[$methodId] = $this->handleAttributes($baseAttributes, $methodId);
            }
        }

        return $loadedItems;
    }

    /**
     * Handle attributes
     *
     * @param ReflectionAttribute[] $attributes
     * @param string                $methodId
     *
     * @return ReflectionAttribute[]
     */
    private function handleAttributes(array $attributes, string $methodId): array
    {
        // Map attribute instances
        $instances = array_map(function (ReflectionAttribute $attribute) {
            return $attribute->newInstance();
        }, $attributes);

        $parsedAttributes = [];

        // Parse attributes
        foreach ($instances as $key => $instance) {
            // Get previous and next attribute
            $hasPrevious = isset($instances[$key - 1]);
            $previous = $hasPrevious ? $attributes[$key - 1] : null;
            $hasNext = isset($instances[$key + 1]);
            $next = $hasNext ? $attributes[$key + 1] : null;

            // Parse attribute
            if ($instance instanceof After) {
                $parsedAttributes[] = new AfterInterceptor()
            }

            elseif ($instance instanceof Around) {

            }

            elseif ($instance instanceof Before) {

            }

            elseif ($instance instanceof AfterThrowing) {

            }

            elseif ($instance instanceof Execution) {

            }

            elseif ($instance instanceof Pointcut) {

            }

            elseif ($instance instanceof And_) {

            }

            elseif ($instance instanceof Not_) {

            }

            elseif ($instance instanceof Or_) {

            }

            else {
                throw new UnexpectedValueException(
                    "Unknown attribute {$instance->getName()} in $methodId"
                );
            }
        }

        return [
            // 'pointcut' =>,
            // 'advice' =>,
        ];
    }

    /**
     * Find attribute
     *
     * @param string $type
     * @param BaseAttribute[] $instances
     * @return BaseAttribute[]
     */
    private function findAttributes(
        string $type,
        array $instances,
    ): array {
        return array_filter($instances, function (BaseAttribute $instance) use ($type) {
            return $instance instanceof $type;
        });
    }
}
