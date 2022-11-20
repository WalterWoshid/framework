<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2011-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Core\Instrument\ClassLoading;

use Go\Aop\Feature;
use Go\Core\Instrument\Singleton;
use Go\Core\Instrument\Transformer\CachingTransformer;
use Go\Core\Instrument\Transformer\ConstructorExecutionTransformer;
use Go\Core\Instrument\Transformer\FilterInjectorTransformer;
use Go\Core\Instrument\Transformer\MagicConstantTransformer;
use Go\Core\Instrument\Transformer\SelfValueTransformer;
use Go\Core\Instrument\Transformer\SourceTransformer;
use Go\Core\Instrument\Transformer\StreamMetaData;
use Go\Core\Instrument\Transformer\WeavingTransformer;
use Go\Core\KernelOptions;
use php_user_filter as PhpStreamFilter;
use RuntimeException;

use function feof;
use function stream_bucket_append;
use function stream_bucket_make_writeable;
use function stream_bucket_new;
use function stream_filter_register;
use function strlen;

/**
 * Php class loader filter for processing php code
 *
 * @property resource $stream Stream instance of underlying resource
 */
class SourceTransformingLoader extends PhpStreamFilter
{
    use Singleton;

    /**
     * Php filter definition
     */
    public const PHP_FILTER_READ = 'php://filter/read=';

    /**
     * Default PHP filter name for registration
     */
    public const FILTER_IDENTIFIER = 'go.source.transforming.loader';

    /**
     * String buffer
     */
    protected string $data = '';

    /**
     * List of transformers
     *
     * @var SourceTransformer[]
     */
    public static array $transformers = [];

    /**
     * Identifier of filter
     */
    protected static string $filterId;

    /**
     * Register current loader as stream filter in PHP
     *
     * @throws RuntimeException If registration was failed
     */
    public static function register(string $filterId = self::FILTER_IDENTIFIER): void
    {
        if (!empty(self::$filterId)) {
            throw new RuntimeException('Stream filter already registered');
        }

        $result = stream_filter_register($filterId, self::class);
        if ($result === false) {
            throw new RuntimeException('Stream filter was not registered');
        }

        self::$filterId = $filterId;

        self::collectTransformers();
    }

    /**
     * Collects all transformers for the source code
     *
     * @return void
     */
    private static function collectTransformers(): void
    {
        // 1. Add the ConstructorExecutionTransformer
        //  - This transformer is responsible for intercepting the execution of the "new" operator
        if (KernelOptions::hasFeature(Feature::INTERCEPT_INITIALIZATIONS)) {
            self::$transformers[] = new ConstructorExecutionTransformer();
        }

        // 2. Add the FilterInjectorTransformer
        //  - This transformer is responsible for intercepting the execution of the "include" and
        //    "require" operators
        //  - The FilterInjectorTransformer needs the "$filterId" to be set, because it is
        //    responsible for registering the SourceTransformingLoader
        FilterInjectorTransformer::setFilterName(self::getId());
        if (KernelOptions::hasFeature(Feature::INTERCEPT_INCLUDES)) {
            self::$transformers[] = new FilterInjectorTransformer();
        }

        // 3. Add the SelfValueTransformer
        //  - This transformer is responsible for replacing the "self" keyword with the actual
        //    class name
        self::$transformers[] = new SelfValueTransformer();

        // 4. Add the WeavingTransformer
        //  - This transformer is responsible for weaving the aspects into the source code
        self::$transformers[] = new WeavingTransformer();

        // 5. Add the MagicConstantTransformer
        //  - This transformer is responsible for replacing the magic "__DIR__" and "__FILE__"
        //    constants with the actual values
        //  - Also "ReflectionClass->getFileName()" is also wrapped into the normalizer method call
        self::$transformers[] = new MagicConstantTransformer();
    }

    /**
     * Returns the name of registered filter
     *
     * @throws RuntimeException if filter was not registered
     */
    public static function getId(): string
    {
        if (empty(self::$filterId)) {
            throw new RuntimeException('Stream filter was not registered');
        }

        return self::$filterId;
    }

    /**
     * {@inheritdoc}
     */
    public function filter($in, $out, &$consumed, $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $this->data .= $bucket->data;
        }

        if ($closing || feof($this->stream)) {
            $consumed = strlen($this->data);

            // $this->stream contains pointer to the source
            $metadata = new StreamMetaData($this->stream, $this->data);
            self::transformCode($metadata);

            $bucket = stream_bucket_new($this->stream, $metadata->getSource());
            stream_bucket_append($out, $bucket);

            return PSFS_PASS_ON;
        }

        return PSFS_FEED_ME;
    }

    /**
     * Adds a SourceTransformer to be applied by this LoadTimeWeaver.
     */
    public static function addTransformer(SourceTransformer $transformer): void
    {
        self::$transformers[] = $transformer;
    }

    /**
     * Transforms source code by passing it through all transformers
     */
    public static function transformCode(StreamMetaData $metadata): void
    {
        $cachingTransformer = CachingTransformer::getInstance();
        $cachingTransformer->transform($metadata);
    }
}
