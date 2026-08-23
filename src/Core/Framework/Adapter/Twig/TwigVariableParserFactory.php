<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig;

use Twig\Environment;

/**
 * @internal
 */
class TwigVariableParserFactory
{
    public function getParser(Environment $twig): TwigVariableParser
    {
        return new TwigVariableParser($twig);
    }
}
