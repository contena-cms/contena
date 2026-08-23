<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Twig\TokenParser;

use Contena\Core\Framework\Adapter\Twig\Node\SwInclude;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

final class ThumbnailTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): SwInclude
    {
        $expr = $this->parser->parseExpression();
        $stream = $this->parser->getStream();

        $className = $expr->getAttribute('value');
        $expr->setAttribute('value', '@Frontend/frontend/utilities/thumbnail.html.twig');

        $variables = new ArrayExpression([], $token->getLine());
        if ($stream->nextIf(Token::NAME_TYPE, 'with')) {
            /** @var ArrayExpression $variables */
            $variables = $this->parser->parseExpression();
        }

        $stream->next();

        $variables->addElement(
            new ConstantExpression($className, $token->getLine()),
            new ConstantExpression('name', $token->getLine())
        );

        return new SwInclude($expr, $variables, false, false, $token->getLine());
    }

    public function getTag(): string
    {
        return 'sw_thumbnails';
    }
}
