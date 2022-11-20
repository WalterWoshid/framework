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
namespace Go\Core\Instrument\Transformer;

use Closure;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class NodeReplacer
{
    private readonly array $searchNodes;

    /**
     * NodeReplacer constructor
     *
     * @param array|string $searchNodes Node(s) to search
     * @param Closure      $replace     Node(s) to replace
     */
    public function __construct(
        array|string $searchNodes,
        private readonly Closure $replace,
    ) {
        $this->searchNodes = is_array($searchNodes) ? $searchNodes : [$searchNodes];
    }

    /**
     * Run the replacer
     *
     * @param Node[] $nodes
     * @return void
     */
    public function run(array $nodes): void
    {
        $traverser = new NodeTraverser();
        $traverser->addVisitor(
            new class ($this->searchNodes, $this->replace) extends NodeVisitorAbstract
            {
                public function __construct(
                    private readonly array   $searchNodes,
                    private readonly Closure $replace,
                ) {}

                public function enterNode(Node $node)
                {
                    if (in_array($node::class, $this->searchNodes, true)) {
                        return ($this->replace)($node);
                    }

                    return null;
                }
            }
        );
        $traverser->traverse($nodes);
    }
}
