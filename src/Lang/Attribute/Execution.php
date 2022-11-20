<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * @author Valentin Wotschel <wotschel.valentin@googlemail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Lang\Attribute;

use Attribute;
use InvalidArgumentException;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeAbstract;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflector\DefaultReflector;
use Roave\BetterReflection\SourceLocator\Type\SingleFileSourceLocator;

/**
 * # Execution attribute
 *
 * Use in combination with {@link \Go\Lang\Attribute\Before Before} or
 * {@link \Go\Lang\Attribute\After After} or {@link \Go\Lang\Attribute\Around Around} or
 * {@link \Go\Lang\Attribute\AfterThrowing AfterThrowing} or
 * {@link \Go\Lang\Attribute\Pointcut Pointcut}
 *
 * <br>
 * Example:
 * ```php
 * use Go\Lang\Attribute\Aspect;
 * use Go\Lang\Attribute\Before;
 * use Go\Lang\Attribute\Execution;
 *
 * #[Aspect]
 * class MyAspect
 * {
 *     #[Before]
 *     #[Execution(class: 'MyClass', method: 'myMethod')]
 *     public function beforeMethodExecution(MethodInvocation $invocation)
 *     {
 *         // Do something before method execution
 *     }
 * }
 * ```
 *
 * Execution accepts the following parameters:
 * - `expression` - {@link \Go\Lang\Attribute\$expressionDoc expression for matching}
 * - `final`      - final methods only (default: false)
 * - `static`     - static methods only (default: false)
 * - `public`     - public visibility (default: false)
 * - `protected`  - protected visibility (default: false)
 * - `private`    - private visibility (default: false)
 *     - If no visibility is specified, then all visibility types are matched
 * - `class`      - class name for matching
 * - `method`     - method name for matching
 * - `function`   - function name for matching
 */
#[Attribute(
    Attribute::IS_REPEATABLE |
    Attribute::TARGET_METHOD |
    Attribute::TARGET_PROPERTY
)]
class Execution implements BaseAttribute
{
    private bool $dynamic;
    private bool $final;
    private bool $static;

    private bool $public;
    private bool $protected;
    private bool $private;

    private string $class;
    private string $method;

    private string $function;
    private bool $matchFunction;

    /**
     * # Execution constructor
     *
     * See {@link \Go\Lang\Attribute\Execution Execution} for more information
     *
     * @param bool        $dynamic   Also matches __call methods
     * @param bool        $final     Final methods only
     * @param bool        $static    Static methods only. With "dynamic = true" also matches
     *                               __callStatic methods
     * @param bool        $public    Public visibility
     * @param bool        $protected Protected visibility
     * @param bool        $private   Private visibility
     * @param string|null $class     Class or namespace for matching
     * @param string|null $method    Method name for matching
     * @param string|null $function  Function name for matching
     */
    public function __construct(
        ?bool   $dynamic    = null,
        ?bool   $final      = null,
        ?bool   $static     = null,
        ?bool   $public     = null,
        ?bool   $protected  = null,
        ?bool   $private    = null,
        ?string $class      = null,
        ?string $method     = null,
        ?string $function   = null,
    ) {
        if (!$class && !$method && !$function) {
            // Get the caller class in backtrace
            $backtrace = debug_backtrace(0, 2)[0];
            // Find the file
            $fileSource = new DefaultReflector(
                new SingleFileSourceLocator(
                    $backtrace['file'],
                    (new BetterReflection)->astLocator()
                )
            );
            // Get ref class
            $refClass = $fileSource->reflectAllClasses()[0];
            // Find the attribute node in the ast
            $parser = (new BetterReflection)->phpParser();
            $ast = $parser->parse(file_get_contents($backtrace['file']));
            $iterator = new RecursiveIteratorIterator(
                new RecursiveArrayIterator($ast),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            $className = $refClass->getName();
            $methodPropName = null;
            foreach ($iterator as $value) {
                if ($value instanceof NodeAbstract) {
                    if ($value->getStartLine() === $backtrace['line']) {
                        $level = $iterator->getDepth();
                        for ($i = $level; $i > 0; $i--) {
                            $parent = $iterator->getSubIterator($i)->current();

                            // ClassMethod
                            if ($parent instanceof ClassMethod) {
                                $methodPropName .= '->' . $parent->name->name . '()';
                                break;
                            }

                            // ClassProperty
                            if ($parent instanceof Property) {
                                $methodPropName .= '->' . $parent->props[0]->name->name;
                                break;
                            }
                        }
                        break;
                    }
                }
            }
            $aspect = $className . $methodPropName;

            throw new InvalidArgumentException(
                "At least one of the \"class\", \"method\" or \"function\" parameters " .
                "must be specified for the #[Execution] attribute in aspect \"$aspect\"."
            );
        }

        // Dynamic matches __call and __callStatic methods
        $this->dynamic = $dynamic !== null ? $dynamic : false;

        $this->final   = $final   !== null ? $final   : true;
        $this->static  = $static  !== null ? $static  : true;

        // Assign visibility if not null
        $this->public    = $public    !== null ? $public    : false;
        $this->protected = $protected !== null ? $protected : false;
        $this->private   = $private   !== null ? $private   : false;

        // If all visibility is false, then set all to true
        if (!$this->public && !$this->protected && !$this->private) {
            $this->public = $this->protected = $this->private = true;
        }

        $this->class  = $class  !== null ? $class  : '*';
        $this->method = $method !== null ? $method : '*';

        $this->function = $function !== null ? $function : '*';
        $this->matchFunction = (bool)$function;
    }
}
