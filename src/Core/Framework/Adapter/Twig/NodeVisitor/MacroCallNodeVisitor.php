<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig\NodeVisitor;

use Contena\Core\Framework\Adapter\Twig\Node\MacroCallExpression;
use Twig\Environment;
use Twig\Node\Expression\MacroReferenceExpression;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * @internal
 */
final class MacroCallNodeVisitor implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        if ($node instanceof MacroReferenceExpression) {
            return new MacroCallExpression($node);
        }

        return $node;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
