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
use InvalidArgumentException;
use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;
use Roave\BetterReflection\BetterReflection;

use function is_resource;

/**
 * Stream metadata object
 */
class StreamMetaData
{
    /**
     * Mapping between array keys and properties
     */
    private static array $propertyMap = [
        'stream_type'  => 'streamType',
        'wrapper_type' => 'wrapperType',
        'wrapper_data' => 'wrapperData',
        'filters'      => 'filterList',
        'uri'          => 'uri',
    ];

    /**
     * A label describing the underlying implementation of the stream.
     */
    public string $streamType;

    /**
     * A label describing the protocol wrapper implementation layered over the stream.
     */
    public string $wrapperType;

    /**
     * Wrapper-specific data attached to this stream.
     *
     * @var mixed
     */
    public mixed $wrapperData;

    /**
     * Array containing the names of any filters that have been stacked onto this stream.
     */
    public array $filterList;

    /**
     * The URI/filename associated with this stream.
     */
    public string $uri;

    /**
     * The initial node tree for this stream
     *
     * @var Node[]
     */
    public array $initialAst;

    /**
     * Creates metadata object from stream
     *
     * @param resource $stream Instance of stream
     * @param string|null $source Source code or null
     * @throws InvalidArgumentException for invalid stream
     */
    public function __construct($stream, string $source = null)
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException('Stream should be valid resource');
        }
        $metadata = stream_get_meta_data($stream);
        if (preg_match('/resource=(.+)$/', $metadata['uri'], $matches)) {
            $metadata['uri'] = PathResolver::realpath($matches[1]);
        }
        foreach ($metadata as $key => $value) {
            if (!isset(self::$propertyMap[$key])) {
                continue;
            }
            $mappedKey = self::$propertyMap[$key];
            $this->$mappedKey = $value;
        }

        // todo only parse when needed
        $parser = (new BetterReflection)->phpParser();
        $this->initialAst = $parser->parse($source);
    }

    /**
     * Returns source code directly from tokens
     *
     * @return string
     */
    public function getSource(): string
    {
        static $printer = new Standard();

        // todo: use printFormatPreserving
        // return $printer->printFormatPreserving($newStmts, $oldStmts, $this->initialAst);
        return $printer->prettyPrintFile($this->initialAst);
    }
}
