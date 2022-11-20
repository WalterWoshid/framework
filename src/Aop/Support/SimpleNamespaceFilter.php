<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Aop\Support;

use Go\Aop\PointFilter;
use Go\ParserReflection\ReflectionFileNamespace;

/**
 * Simple namespace matcher that match only specific namespace name
 *
 * Namespace name can contain wildcards '*', '**' and '?'
 */
class SimpleNamespaceFilter implements PointFilter
{
    /**
     * Namespace name to match, can contain wildcards *,?
     */
    protected string $nsName;

    /**
     * Pattern for regular expression matching
     */
    protected string $regexp;

    /**
     * Namespace name matcher constructor that accepts name or glob pattern to match
     */
    public function __construct(string $namespaceName)
    {
        $namespaceName = trim($namespaceName, '\\');
        $this->nsName  = $namespaceName;
        $this->regexp  = strtr(preg_quote($this->nsName, '/'), [
            '\\*'    => '[^\\\\]+',
            '\\*\\*' => '.+',
            '\\?'    => '.',
            '\\|'    => '|'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function matches(mixed $point, $context = null, $instance = null, array $arguments = null): bool
    {
        $isNamespaceIsObject = ($point === (object) $point);

        if ($isNamespaceIsObject && !$point instanceof ReflectionFileNamespace) {
            return false;
        }

        $nsName = ($point instanceof ReflectionFileNamespace) ? $point->getName() : $point;

        return ($nsName === $this->nsName) || (bool) preg_match("/^(?:{$this->regexp})$/", $nsName);
    }

    /**
     * Returns the kind of point filter
     */
    public function getKind(): int
    {
        return 0;
    }
}
