<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig\Node;

use Contena\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Contena\Core\Framework\Log\Package;
use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Node;
use Twig\Node\NodeOutputInterface;

#[YieldReady]
#[BecomesInternal(version: 'v6.8.0')]
class ReturnNode extends Node implements NodeOutputInterface
{
    public function compile(Compiler $compiler): void
    {
        $compiler->addDebugInfo($this);

        if ($this->hasNode('expr')) {
            $compiler->raw('\Contena\Core\Framework\Adapter\Twig\SwTwigFunction::returnFromMacro(');
            $compiler->subcompile($this->getNode('expr'));
            $compiler->raw(");\n");
        }
        $compiler->write("return;\n");
    }
}
