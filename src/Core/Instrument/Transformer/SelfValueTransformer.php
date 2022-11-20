<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2018-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Core\Instrument\Transformer;

use JetBrains\PhpStorm\ExpectedValues;
use PhpParser\Node;
use PhpParser\Node\Expr\{ClassConstFetch, Closure, Instanceof_, New_, StaticCall};
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\{Catch_, Class_, ClassMethod, Namespace_, Property};
use UnexpectedValueException;

/**
 * Transformer that replaces `self` constants in the source code, e.g. new self()
 */
class SelfValueTransformer implements SourceTransformer
{
    private ?string $namespace = null;
    private ?Name $className = null;

    /**
     * This method may transform the supplied source and return a new replacement for it
     *
     * @return string See RESULT_XXX constants in the interface
     */
    #[ExpectedValues(flagsFromClass: SourceTransformer::class)]
    public function transform(StreamMetaData $metadata): string
    {
        $replacer = new NodeReplacer(
            [
                Namespace_::class, Class_::class, ClassMethod::class, Closure::class,
                Property::class, Param::class, StaticCall::class, ClassConstFetch::class,
                New_::class, Instanceof_::class, Name::class, Catch_::class,
            ],
            function (Node $node) {
                $this->namespace = null;
                $this->className = null;

                switch (true) {
                    // Store namespace
                    case $node instanceof Namespace_:
                        $this->namespace = $node->name->toString();
                        break;

                    // Store class name
                    case $node instanceof Class_:
                        if ($node->name) {
                            $this->className = new Name($node->name->toString());
                        }
                        break;

                    // Resolve return types
                    case $node instanceof ClassMethod:
                    case $node instanceof Closure:
                        if (isset($node->returnType)) {
                            $this->resolveType($node->returnType);
                        }
                        break;

                    // Resolve property types
                    case $node instanceof Property:
                        if (isset($node->type)) {
                            $this->resolveType($node->type);
                        }
                        break;

                    // Resolve param types
                    case $node instanceof Param:
                        if (isset($node->type)) {
                            $this->resolveType($node->type);
                        }
                        break;

                    // Resolve class names
                    case $node instanceof StaticCall:
                    case $node instanceof ClassConstFetch:
                    case $node instanceof New_:
                    case $node instanceof Instanceof_:
                        if ($node->class instanceof Name) {
                            $this->resolveClassName($node->class);
                        }
                        break;

                    // Resolve catch types
                    case $node instanceof Catch_:
                        foreach ($node->types as &$type) {
                            $this->resolveClassName($type);
                        }
                        break;
                }

                return null;
            }
        );
        $replacer->run($metadata->initialAst);

        // We should always vote abstain, because if there are only changes for self we can
        // drop them
        return self::RESULT_ABSTAIN;
    }

    /**
     * Resolve "self" class name with value
     *
     * @param Name $name
     * @return void
     */
    private function resolveClassName(Name &$name): void
    {
        // Skip all names except special "self"
        if (strtolower($name->toString()) !== 'self') {
            return;
        }

        // Save the original name
        $originalName = $name;
        $name = clone $originalName;
        $name->setAttribute('originalName', $originalName);

        $fullClassName    = Name::concat($this->namespace, $this->className);
        $resolvedSelfName = new FullyQualified(
            '\\' . ltrim($fullClassName->toString(), '\\'),
            $name->getAttributes()
        );

        $name = $resolvedSelfName;
    }

    /**
     * Resolve type nodes
     *
     * @param Node $node
     * @return void
     */
    private function resolveType(Node $node): void
    {
        switch (true) {
            // Resolve nullable types
            case $node instanceof NullableType:
                $this->resolveType($node->type);
                return;

            // Resolve class names
            case $node instanceof Name:
                $this->resolveClassName($node);
                return;

            // Resolve identifiers
            case $node instanceof Identifier:
                return;
        }

        // todo: create exception handler so transformer exceptions can be handled
        //  and the original source code can be returned
        throw new UnexpectedValueException('Unknown node type: ' . get_class($node));
    }
}
