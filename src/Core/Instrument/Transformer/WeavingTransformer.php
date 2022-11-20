<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2011-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Core\Instrument\Transformer;

use Go\Aop\Advisor;
use Go\Aop\Feature;
use Go\Aop\Framework\AbstractJoinpoint;
use Go\Core\Instrument\ReflectionHelper;
use Go\Core\KernelOptions;
use Go\Core\Proxy\ClassProxyGenerator;
use Go\Core\Proxy\FunctionProxyGenerator;
use Go\Core\Proxy\TraitProxyGenerator;
use Go\Core\Service\AdviceMatcher;
use Go\Core\Service\AspectLoader;
use Go\Lang\Attribute\Aspect;
use JetBrains\PhpStorm\ExpectedValues;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Roave\BetterReflection\Reflection\ReflectionFunction;

/**
 * Main transformer that performs weaving of aspects into the source code
 */
class WeavingTransformer implements SourceTransformer
{
    /**
     * This method may transform the supplied source and return a new replacement for it
     *
     * @param StreamMetaData $metadata
     *
     * @return string See RESULT_XXX constants in the interface
     *
     * @throws ReflectionException
     */
    #[ExpectedValues(flagsFromClass: SourceTransformer::class)]
    public function transform(StreamMetaData $metadata): string
    {
        $totalTransformations = 0;
        $reflector = ReflectionHelper::findReflectorByPath($metadata->uri);
        $classes   = $reflector->reflectAllClasses();
        $functions = $reflector->reflectAllFunctions();

        // Check if we have some new aspects that weren't loaded yet
        $unloadedAspects = AspectLoader::getUnloadedAspects();
        if (!empty($unloadedAspects)) {
            $this->loadAndRegisterAspects($unloadedAspects);
        }
        // $advisors = $this->container->getByTag('advisor');
        // todo
        $advisors = [];

        // Process classes
        foreach ($classes as $class) {
            // Skip interfaces and aspects
            $attributes = $class->getAttributesByName(Aspect::class);
            if ($class->isInterface() || !empty($attributes)) {
                continue;
            }
            $wasClassProcessed = $this->processSingleClass(
                $advisors,
                $metadata,
                $class,
            );
            $totalTransformations += (integer) $wasClassProcessed;
        }

        // Process functions
        $wasFunctionsProcessed = $this->processFunctions($advisors, $metadata, $functions);
        $totalTransformations += (integer) $wasFunctionsProcessed;

        $result = ($totalTransformations > 0) ? self::RESULT_TRANSFORMED : self::RESULT_ABSTAIN;

        return $result;
    }

    /**
     * Performs weaving of single class if needed, returns true if the class was processed
     *
     * @param Advisor[] $advisors List of advisors
     * @param StreamMetaData $metadata
     * @param \Roave\BetterReflection\Reflection\ReflectionClass $class
     *
     * @return bool
     *
     * @throws ReflectionException
     */
    private function processSingleClass(
        array $advisors,
        StreamMetaData $metadata,
        \Roave\BetterReflection\Reflection\ReflectionClass $class,
    ): bool {
        $useParameterWidening = KernelOptions::hasFeature(Feature::PARAMETER_WIDENING);

        $advices = AdviceMatcher::getAdvicesForClass($class, $advisors);

        if (empty($advices)) {
            // Fast return if there aren't any advices for that class
            return false;
        }

        // Sort advices in advance to keep the correct order in cache, and leave only keys for the cache
        $advices = AbstractJoinpoint::flatAndSortAdvices($advices);

        // Prepare new class name
        $newClassName = $class->getShortName() . KernelOptions::AOP_PROXIED_SUFFIX;

        // Replace original class name with new
        $this->adjustOriginalClass($class, $advices, $metadata, $newClassName);
        $newParentName = $class->getNamespaceName() . '\\' . $newClassName;

        // Prepare child Aop proxy
        $childProxyGenerator = $class->isTrait()
            ? new TraitProxyGenerator($class, $newParentName, $advices, $useParameterWidening)
            : new ClassProxyGenerator($class, $newParentName, $advices, $useParameterWidening);

        $refNamespace = new ReflectionFileNamespace($class->getFileName(), $class->getNamespaceName());
        foreach ($refNamespace->getNamespaceAliases() as $fqdn => $alias) {
            // Either we have a string or Identifier node
            if ($alias !== null) {
                $childProxyGenerator->addUse($fqdn, (string) $alias);
            } else {
                $childProxyGenerator->addUse($fqdn);
            }
        }

        $childCode = $childProxyGenerator->generate();

        if ($useStrictMode) {
            $childCode = 'declare(strict_types =1 );' . PHP_EOL . $childCode;
        }

        $contentToInclude = $this->saveProxyToCache($class, $childCode);

        // Get last token for this class
        $lastClassToken = $class->getNode()->getAttribute('endFilePos');

        $metadata->tokenStream[$lastClassToken][1] .= PHP_EOL . $contentToInclude;

        return true;
    }

    /**
     * Adjust definition of original class source to enable extending
     *
     * @param array $advices List of class advices (used to check for final methods and make them non-final)
     */
    private function adjustOriginalClass(
        ReflectionClass $class,
        array $advices,
        StreamMetaData $streamMetaData,
        string $newClassName
    ): void {
        $classNode = $class->getNode();
        $position  = $classNode->getAttribute('startFilePos');
        do {
            if (isset($streamMetaData->tokenStream[$position])) {
                $token = $streamMetaData->tokenStream[$position];
                // Remove final and following whitespace from the class, child will be final instead
                if ($token[0] === T_FINAL) {
                    unset($streamMetaData->tokenStream[$position], $streamMetaData->tokenStream[$position+1]);
                }
                // First string is class/trait name
                if ($token[0] === T_STRING) {
                    $streamMetaData->tokenStream[$position][1] = $newClassName;
                    // We have finished our job, can break this loop
                    break;
                }
            }
            ++$position;
        } while (true);

        foreach ($class->getMethods(ReflectionMethod::IS_FINAL) as $finalMethod) {
            if (!$finalMethod instanceof ReflectionMethod || $finalMethod->getDeclaringClass()->name !== $class->name) {
                continue;
            }
            $hasDynamicAdvice = isset($advices[AspectContainer::METHOD_PREFIX][$finalMethod->name]);
            $hasStaticAdvice  = isset($advices[AspectContainer::STATIC_METHOD_PREFIX][$finalMethod->name]);
            if (!$hasDynamicAdvice && !$hasStaticAdvice) {
                continue;
            }
            $methodNode = $finalMethod->getNode();
            $position   = $methodNode->getAttribute('startFilePos');
            do {
                if (isset($streamMetaData->tokenStream[$position])) {
                    $token = $streamMetaData->tokenStream[$position];
                    // Remove final and following whitespace from the method, child will be final instead
                    if ($token[0] === T_FINAL) {
                        unset($streamMetaData->tokenStream[$position], $streamMetaData->tokenStream[$position+1]);
                        break;
                    }
                }
                ++$position;
            } while (true);
        }
    }

    /**
     * Performs weaving of functions in the current namespace, returns true if functions were processed, false otherwise
     *
     * @param Advisor[] $advisors List of advisors
     * @param StreamMetaData $metadata
     * @param ReflectionFunction[] $reflectionFunctions
     *
     * @return bool
     *
     * @throws ReflectionException
     */
    private function processFunctions(
        array $advisors,
        StreamMetaData $metadata,
        array $reflectionFunctions
    ): bool {
        $cacheKey = '';
        $cacheAdapter = KernelOptions::getCacheAdapter();

        $wasProcessedFunctions = false;
        // $functionAdvices = $this->adviceMatcher->getAdvicesForFunctions($namespace, $advisors);
        $functionAdvices = []; // todo
        if (!empty($functionAdvices)) {
            $cacheDir        = KernelOptions::getCacheDir();
            $cacheDir .= $cacheKey;
            $fileName = str_replace('\\', '/', $namespace->getName()) . '.php';

            $functionFileName = $cacheDir . $fileName;
            if (!file_exists($functionFileName) || !$this->container->isFresh(filemtime($functionFileName))) {
                $functionAdvices = AbstractJoinpoint::flatAndSortAdvices($functionAdvices);
                $dirname         = dirname($functionFileName);
                if (!file_exists($dirname)) {
                    mkdir($dirname, $this->options['cacheFileMode'], true);
                }
                $useParameterWidening = KernelOptions::hasFeature(Feature::PARAMETER_WIDENING);
                $generator = new FunctionProxyGenerator($namespace, $functionAdvices, $useParameterWidening);
                file_put_contents($functionFileName, $generator->generate(), LOCK_EX);
                // For cache files we don't want executable bits by default
                chmod($functionFileName, $this->options['cacheFileMode'] & (~0111));
            }
            $content = 'include_once AOP_CACHE_DIR . ' . var_export($cacheKey . $fileName, true) . ';';

            $lastTokenPosition = $namespace->getLastTokenPosition();
            $metadata->tokenStream[$lastTokenPosition][1] .= PHP_EOL . $content;
            $wasProcessedFunctions = true;
        }

        return $wasProcessedFunctions;
    }

    /**
     * Save AOP proxy to the separate file anr returns the php source code for inclusion
     */
    private function saveProxyToCache(ReflectionClass $class, string $childCode): string
    {
        static $cacheDirSuffix = '/_proxies/';

        $cacheDir          = $this->cachePathManager->getCacheDir() . $cacheDirSuffix;
        $relativePath      = str_replace($this->options['appDir'] . DIRECTORY_SEPARATOR, '', $class->getFileName());
        $proxyRelativePath = str_replace('\\', '/', $relativePath . '/' . $class->getName() . '.php');
        $proxyFileName     = $cacheDir . $proxyRelativePath;
        $dirname           = dirname($proxyFileName);
        if (!file_exists($dirname)) {
            mkdir($dirname, $this->options['cacheFileMode'], true);
        }

        $body = '<?php' . PHP_EOL . $childCode;

        $isVirtualSystem = str_starts_with($proxyFileName, 'vfs');
        file_put_contents($proxyFileName, $body, $isVirtualSystem ? 0 : LOCK_EX);
        // For cache files we don't want executable bits by default
        chmod($proxyFileName, $this->options['cacheFileMode'] & (~0111));

        return 'include_once AOP_CACHE_DIR . ' . var_export($cacheDirSuffix . $proxyRelativePath, true) . ';';
    }

    /**
     * Utility method to load and register unloaded aspects
     *
     * @param array $unloadedAspects List of unloaded aspects
     */
    private function loadAndRegisterAspects(array $unloadedAspects): void
    {
        foreach ($unloadedAspects as $unloadedAspect) {
            AspectLoader::loadAndRegister($unloadedAspect);
        }
    }
}
