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

use Go\Core\KernelOptions;
use JetBrains\PhpStorm\ExpectedValues;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\MagicConst\{Dir, File};
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;

/**
 * Transformer that replaces magic "__DIR__" and "__FILE__" constants in the source code
 *
 * Additionally, ReflectionClass->getFileName() is also wrapped into normalizer method call
 */
class MagicConstantTransformer implements SourceTransformer
{
    /**
     * This method may transform the supplied source and return a new replacement for it
     *
     * @return string See RESULT_XXX constants in the interface
     */
    #[ExpectedValues(flagsFromClass: SourceTransformer::class)]
    public function transform(StreamMetaData $metadata): string
    {
        $this->replaceMagicDirFileConstants($metadata);
        $this->wrapReflectionGetFileName($metadata);

        // We should always vote abstain, because if there is only changes for magic constants, we
        // can drop them
        return self::RESULT_ABSTAIN;
    }

    /**
     * Resolves file name from the cache directory to the real application root dir
     */
    public static function resolveFileName(string $fileName): string
    {
        $suffix = '.php';
        $pathParts = explode($suffix, str_replace(
            [KernelOptions::getCacheDir(), DIRECTORY_SEPARATOR . '_proxies'],
            [KernelOptions::getAppDir(), ''],
            $fileName
        ));
        // throw away namespaced path from actual filename
        return $pathParts[0] . $suffix;
    }

    /**
     * Wraps all possible getFileName() methods from ReflectionFile
     */
    private function wrapReflectionGetFileName(StreamMetaData $metadata): void
    {
        $replacer = new NodeReplacer(
            MethodCall::class,
            function (MethodCall $methodCallNode) {
                if ($methodCallNode->name instanceof Identifier
                    && $methodCallNode->name->toString() === 'getFileName'
                ) {
                    $startPosition    = $methodCallNode->getAttribute('startFilePos');
                    $endPosition      = $methodCallNode->getAttribute('endFilePos');
                    $expressionPrefix = '\\' . self::class . '::resolveFileName(';

                    // $metadata->tokenStream[$startPosition][1] = $expressionPrefix . $metadata->tokenStream[$startPosition][1];
                    // $metadata->tokenStream[$endPosition][1] .= ')';
                }
            }
        );

        $replacer->run($metadata->initialAst);
    }

    /**
     * Replaces all magic __DIR__ and __FILE__ constants in the file with calculated value
     */
    private function replaceMagicDirFileConstants(StreamMetaData $metadata): void
    {
        $magicFileValue = $metadata->uri;
        $magicDirValue  = dirname($metadata->uri);

        $replacer = new NodeReplacer(
            [Dir::class, File::class],
            function (Dir|File $node) use ($magicDirValue, $magicFileValue) {
                return new String_($node instanceof Dir ? $magicDirValue : $magicFileValue);
            }
        );

        $replacer->run($metadata->initialAst);
    }
}
