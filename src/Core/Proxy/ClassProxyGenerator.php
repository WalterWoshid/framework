<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Core\Proxy;

use Go\Aop\Advice;
use Go\Aop\Framework\ClassFieldAccess;
use Go\Aop\Framework\DynamicClosureMethodInvocation;
use Go\Aop\Framework\ReflectionConstructorInvocation;
use Go\Aop\Framework\StaticClosureMethodInvocation;
use Go\Aop\Framework\StaticInitializationJoinpoint;
use Go\Aop\Intercept\Joinpoint;
use Go\Aop\Proxy;
use Go\Core\AspectKernel;
use Go\Core\KernelOptions;
use Go\Core\Proxy\Part\FunctionCallArgumentListGenerator;
use Go\Core\Proxy\Part\InterceptedConstructorGenerator;
use Go\Core\Proxy\Part\InterceptedMethodGenerator;
use Go\Core\Proxy\Part\JoinPointPropertyGenerator;
use Go\Core\Proxy\Part\PropertyInterceptionTrait;
use Go\Core\Service\LazyAdvisorAccessor;
use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\DocBlockGenerator;
use Laminas\Code\Reflection\DocBlockReflection;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use UnexpectedValueException;

/**
 * Class proxy builder that is used to generate a child class from the list of joinpoints
 */
class ClassProxyGenerator
{
    /**
     * Static mappings for class name for excluding if-else check
     */
    protected static array $invocationClassMap = [
        KernelOptions::METHOD_PREFIX        => DynamicClosureMethodInvocation::class,
        KernelOptions::STATIC_METHOD_PREFIX => StaticClosureMethodInvocation::class,
        KernelOptions::PROPERTY_PREFIX      => ClassFieldAccess::class,
        KernelOptions::STATIC_INIT_PREFIX   => StaticInitializationJoinpoint::class,
        KernelOptions::INIT_PREFIX          => ReflectionConstructorInvocation::class
    ];

    /**
     * List of advices that are used for generation of child
     */
    protected array $adviceNames = [];

    /**
     * Instance of class generator
     */
    protected ClassGenerator $generator;

    /**
     * Should parameter widening be used or not
     */
    protected bool $useParameterWidening;

    /**
     * Generates a child code by original class reflection and joinpoints for it
     *
     * @param ReflectionClass $originalClass Original class reflection
     * @param string $parentClassName Parent class name to use
     * @param string[][]|string[][][] $classAdviceNames List of advices for class
     * @param bool $useParameterWidening Enables usage of parameter widening feature
     * @throws ReflectionException
     */
    public function __construct(
        ReflectionClass $originalClass,
        string $parentClassName,
        array $classAdviceNames,
        bool $useParameterWidening
    ) {
        $this->adviceNames          = $classAdviceNames;
        $this->useParameterWidening = $useParameterWidening;

        $dynamicMethodAdvices  = $classAdviceNames[KernelOptions::METHOD_PREFIX] ?? [];
        $staticMethodAdvices   = $classAdviceNames[KernelOptions::STATIC_METHOD_PREFIX] ?? [];
        $propertyAdvices       = $classAdviceNames[KernelOptions::PROPERTY_PREFIX] ?? [];
        $interceptedMethods    = array_keys($dynamicMethodAdvices + $staticMethodAdvices);
        $interceptedProperties = array_keys($propertyAdvices);
        $introducedInterfaces  = $classAdviceNames[KernelOptions::INTRODUCTION_INTERFACE_PREFIX]['root'] ?? [];
        $introducedTraits      = $classAdviceNames[KernelOptions::INTRODUCTION_TRAIT_PREFIX]['root'] ?? [];

        $generatedProperties = [new JoinPointPropertyGenerator($classAdviceNames)];
        $generatedMethods    = $this->interceptMethods($originalClass, $interceptedMethods);

        $introducedInterfaces[] = '\\' . Proxy::class;

        if (!empty($interceptedProperties)) {
            $generatedMethods['__construct'] = new InterceptedConstructorGenerator(
                $interceptedProperties,
                $originalClass->getConstructor(),
                $generatedMethods['__construct'] ?? null,
                $useParameterWidening
            );
            $introducedTraits[] = '\\' . PropertyInterceptionTrait::class;
        }

        $this->generator = new ClassGenerator(
            $originalClass->getShortName(),
            $originalClass->getNamespaceName(),
            $originalClass->isFinal() ? ClassGenerator::FLAG_FINAL : null,
            $parentClassName,
            $introducedInterfaces,
            $generatedProperties,
            $generatedMethods
        );
        if ($originalClass->getDocComment()) {
            $reflectionDocBlock = new DocBlockReflection($originalClass->getDocComment());
            $this->generator->setDocBlock(DocBlockGenerator::fromReflection($reflectionDocBlock));
        }

        $this->generator->addTraits($introducedTraits);
    }

    /**
     * Adds use alias for this class
     */
    public function addUse(string $use, string $useAlias = null): void
    {
        $this->generator->addUse($use, $useAlias);
    }

    /**
     * Inject advices into given class
     *
     * NB This method will be used as a callback during source code evaluation to inject joinpoints
     * @throws ReflectionException
     */
    public static function injectJoinPoints(string $targetClassName): void
    {
        $reflectionClass    = new ReflectionClass($targetClassName);
        $joinPointsProperty = $reflectionClass->getProperty(JoinPointPropertyGenerator::NAME);

        $advices    = $joinPointsProperty->getValue();
        $joinPoints = static::wrapWithJoinPoints($advices, $reflectionClass->getParentClass()->name);
        $joinPointsProperty->setValue($joinPoints);

        $staticInit = KernelOptions::STATIC_INIT_PREFIX . ':root';
        if (isset($joinPoints[$staticInit])) {
            ($joinPoints[$staticInit])();
        }
    }

    /**
     * Generates the source code of child class
     */
    public function generate(): string
    {
        $classCode = $this->generator->generate();

        return $classCode
            // Inject advices on call
            . '\\' . self::class . '::injectJoinPoints(' . $this->generator->getName() . '::class);';
    }

    /**
     * Wrap advices with joinpoint object
     *
     * NB: Extension should be responsible for wrapping advice with join point
     *
     * @param array|Advice[][][] $classAdvices Advices for specific class
     *
     * @return Joinpoint[] returns list of joinpoint ready to use
     *
     * @throws UnexpectedValueException If joinPoint type is unknown
     */
    protected static function wrapWithJoinPoints(array $classAdvices, string $className): array
    {
        /** @var ?LazyAdvisorAccessor $accessor */
        static $accessor = null;

        if (!isset($accessor)) {
            $aspectKernel = AspectKernel::getInstance();
            $accessor     = LazyAdvisorAccessor::getInstance();
        }

        $joinPoints = [];

        foreach ($classAdvices as $joinPointType => $typedAdvices) {
            // if not isset then we don't want to create such invocation for class
            if (!isset(self::$invocationClassMap[$joinPointType])) {
                continue;
            }
            foreach ($typedAdvices as $joinPointName => $advices) {
                $filledAdvices = [];
                foreach ($advices as $advisorName) {
                    $filledAdvices[] = $accessor->$advisorName;
                }

                $joinpoint = new self::$invocationClassMap[$joinPointType]($filledAdvices, $className, $joinPointName);
                $joinPoints["$joinPointType:$joinPointName"] = $joinpoint;
            }
        }

        return $joinPoints;
    }

    /**
     * Returns list of intercepted method generators for class by method names
     *
     * @param string[] $methodNames List of methods to intercept
     *
     * @return InterceptedMethodGenerator[]
     *
     * @throws ReflectionException
     */
    protected function interceptMethods(ReflectionClass $originalClass, array $methodNames): array
    {
        $interceptedMethods = [];
        foreach ($methodNames as $methodName) {
            $reflectionMethod = $originalClass->getMethod($methodName);
            $methodBody       = $this->getJoinpointInvocationBody($reflectionMethod);

            $interceptedMethods[$methodName] = new InterceptedMethodGenerator(
                $reflectionMethod,
                $methodBody,
                $this->useParameterWidening
            );
        }

        return $interceptedMethods;
    }

    /**
     * Creates string definition for method body by method reflection
     */
    protected function getJoinpointInvocationBody(ReflectionMethod $method): string
    {
        $isStatic = $method->isStatic();
        $scope    = $isStatic ? 'static::class' : '$this';
        $prefix   = $isStatic ? KernelOptions::STATIC_METHOD_PREFIX : KernelOptions::METHOD_PREFIX;

        $argumentList = new FunctionCallArgumentListGenerator($method);
        $argumentCode = $argumentList->generate();
        $return       = 'return ';
        if ($method->hasReturnType()) {
            $returnType = $method->getReturnType();
            if ($returnType instanceof ReflectionNamedType && $returnType->getName() === 'void') {
                // void return types should not return anything
                $return = '';
            }
        }

        if (!empty($argumentCode)) {
            $scope = "$scope, $argumentCode";
        }

        return "{$return}self::\$__joinPoints['$prefix:$method->name']->__invoke($scope);";
    }
}
