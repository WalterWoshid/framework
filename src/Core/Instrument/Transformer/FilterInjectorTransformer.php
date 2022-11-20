<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Core\Instrument\Transformer;

use Go\Core\Instrument\PathResolver;
use Go\Core\KernelOptions;
use Go\Core\Service\CachePathManager;
use JetBrains\PhpStorm\ExpectedValues;
use PhpParser\Node\Expr\Include_;
use PhpParser\NodeTraverser;

/**
 * Transformer that injects source filter for "require" and "include" operations
 */
class FilterInjectorTransformer implements SourceTransformer
{
    /**
     * Php filter definition
     */
    public const PHP_FILTER_READ = 'php://filter/read=';

    /**
     * Name of the filter to inject
     *
     * @var ?string
     */
    protected static ?string $filterName = null;

    /**
     * Set filter name
     *
     * @param string $filterName
     *
     * @return void
     */
    public static function setFilterName(string $filterName)
    {
        self::$filterName = $filterName;
    }

    /**
     * Replace source path with correct one
     *
     * This operation can check for cache, can rewrite paths, add additional filters and much more
     *
     * @param string $originalResource Initial resource to include
     * @param string $originalDir Path to the directory from where include was called for resolving relative resources
     */
    public static function rewrite(string $originalResource, string $originalDir = ''): string
    {
        $cacheDir = KernelOptions::getCacheDir();
        $debug    = KernelOptions::isDebug();

        $resource = $originalResource;
        if ($resource[0] !== '/') {
            $resource
                =  PathResolver::realpath($resource, true)
                ?: PathResolver::realpath("$originalDir/$resource", true)
                ?: $originalResource;
        }
        $cachedResource = CachePathManager::getCachePathForResource($resource);

        // If the cache is disabled or no cache yet, then use on-fly method
        if (!$cacheDir || $debug || !file_exists($cachedResource)) {
            return self::PHP_FILTER_READ . self::$filterName . '/resource=' . $resource;
        }

        return $cachedResource;
    }

    /**
     * Wrap all includes into rewrite filter
     *
     * @return string See RESULT_XXX constants in the interface
     */
    #[ExpectedValues(flagsFromClass: SourceTransformer::class)]
    public function transform(StreamMetaData $metadata): string
    {
        $includeExpressionFinder = new NodeFinderVisitor([Include_::class]);

        // TODO: move this logic into walkSyntaxTree(Visitor $nodeVistor) method
        $traverser = new NodeTraverser();
        $traverser->addVisitor($includeExpressionFinder);
        $traverser->traverse($metadata->initialAst);

        /** @var Include_[] $includeExpressions */
        $includeExpressions = $includeExpressionFinder->getFoundNodes();

        if (empty($includeExpressions)) {
            return self::RESULT_ABSTAIN;
        }

        foreach ($includeExpressions as $includeExpression) {
            $startPosition = $includeExpression->getAttribute('startFilePos');
            $endPosition   = $includeExpression->getAttribute('endFilePos');

            $metadata->tokenStream[$startPosition][1] .= ' \\' . self::class . '::rewrite(';
            if ($metadata->tokenStream[$startPosition+1][0] === T_WHITESPACE) {
                unset($metadata->tokenStream[$startPosition+1]);
            }

            $metadata->tokenStream[$endPosition][1] .= ', __DIR__)';
        }

        return self::RESULT_TRANSFORMED;
    }
}
