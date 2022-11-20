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

use Go\Core\Instrument\ClassLoading\SourceTransformingLoader;
use Go\Core\Instrument\Singleton;
use Go\Core\Service\CachePathManager;
use Go\Core\Service\GoAspectContainer;

use JetBrains\PhpStorm\ExpectedValues;

use function dirname;

/**
 * Caching transformer that is able to take the transformed source from a cache
 */
class CachingTransformer implements SourceTransformer
{
    use Singleton;

    /**
     * Mask of permission bits for cache files.
     * By default, permissions are affected by the umask system setting
     *
     * @var int-mask
     */
    protected int $cacheFileMode = 0770;

    /**
     * This method may transform the supplied source and return a new replacement for it
     *
     * @return string See RESULT_XXX constants in the interface
     */
    #[ExpectedValues(flagsFromClass: SourceTransformer::class)]
    public function transform(StreamMetaData $metadata): string
    {
        $originalUri      = $metadata->uri;
        $processingResult = self::RESULT_ABSTAIN;
        $cacheUri         = CachePathManager::getCompiledCachePathForResource($originalUri);

        // Guard to disable overwriting of original files
        if ($cacheUri === $originalUri) {
            return self::RESULT_ABORTED;
        }

        // Query cache state by file modification time
        $lastModified  = filemtime($originalUri);
        $cacheState    = CachePathManager::queryCacheState($originalUri);
        $cacheModified = $cacheState ? $cacheState['filemtime'] : 0;

        // If cache modified time is less than original
        if ($cacheModified < $lastModified
            // or cache uri doesn't match current uri
            || (isset($cacheState['cacheUri']) && $cacheState['cacheUri'] !== $cacheUri)
            // Or cache isn't fresh
            || !GoAspectContainer::isFresh($cacheModified)
        ) {
            // Process all transformers
            $processingResult = $this->processTransformers($metadata);

            // If we have a result, then we should write it to the cache
            if ($processingResult === SourceTransformer::RESULT_TRANSFORMED) {
                $parentCacheDir = dirname($cacheUri);
                if (!is_dir($parentCacheDir)) {
                    mkdir($parentCacheDir, $this->cacheFileMode, true);
                }
                file_put_contents($cacheUri, $metadata->source, LOCK_EX);
                // For cache files we don't want executable bits by default
                chmod($cacheUri, $this->cacheFileMode & (~0111));
            }

            $filemtime = $_SERVER['REQUEST_TIME'] ?? time();

            // Update cache uri based on result
            $cacheUri = $processingResult === SourceTransformer::RESULT_TRANSFORMED
                ? $cacheUri
                : null;

            // Update cache state
            CachePathManager::setCacheState(
                resource:  $originalUri,
                filemtime: $filemtime,
                cacheUri:  $cacheUri,
            );

            return $processingResult;
        }

        if ($cacheState) {
            $processingResult = isset($cacheState['cacheUri']) ? self::RESULT_TRANSFORMED : self::RESULT_ABORTED;
        }
        if ($processingResult === self::RESULT_TRANSFORMED) {
            // Just replace all tokens in the stream
            ReflectionEngine::parseFile($cacheUri);
            $metadata->setTokenStreamFromRawTokens(
                ReflectionEngine::getLexer()
                                ->getTokens()
            );
        }

        return $processingResult;
    }

    /**
     * Iterates over transformers
     *
     * @return string See RESULT_XXX constants in the interface
     */
    #[ExpectedValues(flagsFromClass: SourceTransformer::class)]
    private function processTransformers(StreamMetaData $metadata): string
    {
        $overallResult = SourceTransformer::RESULT_ABSTAIN;
        foreach (SourceTransformingLoader::$transformers as $transformer) {
            $transformationResult = $transformer->transform($metadata);
            if ($overallResult === SourceTransformer::RESULT_ABSTAIN
                && $transformationResult === SourceTransformer::RESULT_TRANSFORMED
            ) {
                $overallResult = SourceTransformer::RESULT_TRANSFORMED;
            }

            // If transformer reported a termination, next transformers will be skipped
            if ($transformationResult === SourceTransformer::RESULT_ABORTED) {
                $overallResult = SourceTransformer::RESULT_ABORTED;
                break;
            }
        }

        return $overallResult;
    }
}
