<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig;

use Contena\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Contena\Core\Framework\Log\Package;
use Twig\Environment;

#[BecomesInternal(version: 'v6.8.0')]
class TwigVariableParserFactory
{
    public function getParser(Environment $twig): TwigVariableParser
    {
        return new TwigVariableParser($twig);
    }
}
