<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig\Node;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Node;

/**
 * @internal
 */
#[YieldReady]
class FeatureCallSilentToken extends Node
{
    public function __construct(
        private readonly string $flag,
        Node $body,
        int $line,
    ) {
        parent::__construct(['body' => $body], [], $line);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->addDebugInfo($this)
            ->raw('\Contena\Core\Framework\Feature::callSilentIfInactive(')
            ->string($this->flag)
            ->raw(', function () use(&$context) { ')
            ->subcompile($this->getNode('body'))
            ->raw('});');
    }
}
