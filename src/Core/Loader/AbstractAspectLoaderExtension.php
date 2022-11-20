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

use Dissect\Lexer\Exception\RecognitionException;
use Dissect\Lexer\TokenStream\TokenStream;
use Dissect\Parser\Exception\UnexpectedTokenException;
use Go\Aop\PointFilter;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use UnexpectedValueException;

use function get_class;

/**
 * Abstract aspect loader
 */
abstract class AbstractAspectLoaderExtension implements AspectLoaderExtension
{
    /**
     * Default loader constructor that accepts pointcut lexer and parser
     *
     * @param TagAwareAdapter $abstractAdapter Instance of abstract adapter
     */
    public function __construct(protected TagAwareAdapter $abstractAdapter) {}

    /**
     * General method for parsing pointcuts
     *
     * @param object $aspect
     * @param ReflectionMethod|ReflectionProperty $reflection Reflection of point
     * @param string $pointcutExpression
     *
     * @return PointFilter
     *
     * @throws UnexpectedValueException if there was an error during parsing
     */
    final protected function parsePointcut(
        object $aspect,
        ReflectionMethod|ReflectionProperty $reflection,
        string $pointcutExpression
    ): PointFilter {
        $stream = $this->makeLexicalAnalyze($aspect, $reflection, $pointcutExpression);

        return $this->parseTokenStream($reflection, $pointcutExpression, $stream);
    }

    /**
     * Performs lexical analyze of pointcut
     *
     * @param object $aspect
     * @param ReflectionMethod|ReflectionProperty $reflection
     * @param string $pointcutExpression
     *
     * @return TokenStream
     *
     * @throws UnexpectedValueException
     */
    private function makeLexicalAnalyze(
        object $aspect,
        ReflectionMethod|ReflectionProperty $reflection,
        string $pointcutExpression
    ): TokenStream {
        try {
            $resolvedThisPointcut = str_replace('$this', get_class($aspect), $pointcutExpression);
            $stream = $this->pointcutLexer->lex($resolvedThisPointcut);
        } catch (RecognitionException $e) {
            $message = 'Can not recognize the lexical structure `%s` before %s, defined in %s:%d';
            $message = sprintf(
                $message,
                $pointcutExpression,
                (isset($reflection->class) ? $reflection->class . '->' : '') . $reflection->name,
                method_exists($reflection, 'getFileName')
                    ? $reflection->getFileName()
                    : $reflection->getDeclaringClass()->getFileName(),
                method_exists($reflection, 'getStartLine')
                    ? $reflection->getStartLine()
                    : 0
            );
            throw new UnexpectedValueException($message, 0, $e);
        }

        return $stream;
    }

    /**
     * Performs parsing of pointcut
     *
     * @param ReflectionMethod|ReflectionProperty $reflection
     * @param string $pointcutExpression
     * @param TokenStream $stream
     *
     * @return PointFilter
     *
     * @throws UnexpectedValueException
     */
    private function parseTokenStream(
        ReflectionMethod|ReflectionProperty $reflection,
        string $pointcutExpression,
        TokenStream $stream
    ): PointFilter {
        try {
            $pointcut = $this->pointcutParser->parse($stream);
        } catch (UnexpectedTokenException $e) {
            $token   = $e->getToken();
            $message = 'Unexpected token %s in the `%s` before %s, defined in %s:%d.' . PHP_EOL;
            $message .= 'Expected one of: %s';
            $message = sprintf(
                $message,
                $token->getValue(),
                $pointcutExpression,
                (isset($reflection->class) ? $reflection->class . '->' : '') . $reflection->name,
                method_exists($reflection, 'getFileName')
                    ? $reflection->getFileName()
                    : $reflection->getDeclaringClass()->getFileName(),
                method_exists($reflection, 'getStartLine')
                    ? $reflection->getStartLine()
                    : 0,
                implode(', ', $e->getExpected())
            );
            throw new UnexpectedValueException($message, 0, $e);
        }

        return $pointcut;
    }
}
