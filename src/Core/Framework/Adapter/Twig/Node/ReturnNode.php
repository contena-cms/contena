<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig\Node;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Node;
use Twig\Node\NodeOutputInterface;

/**
 * @internal
 */
#[YieldReady]
class ReturnNode extends Node implements NodeOutputInterface
{
    public function compile(Compiler $compiler): void
    {
        $compiler->addDebugInfo($this);

        if ($this->hasNode('expr')) {
            $compiler->raw('\Contena\Core\Framework\Adapter\Twig\SwTwigFunction::$macroResult = ');
            $compiler->subcompile($this->getNode('expr'));
            $compiler->raw(";\n");
        }
        $compiler->write("return;\n");
    }
}
