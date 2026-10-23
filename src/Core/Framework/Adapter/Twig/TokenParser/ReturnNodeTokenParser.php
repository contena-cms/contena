<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig\TokenParser;

use Contena\Core\Framework\Adapter\Twig\Node\ReturnNode;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * @internal
 */
final class ReturnNodeTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): ReturnNode
    {
        $stream = $this->parser->getStream();
        $nodes = [];

        if (!$stream->test(Token::BLOCK_END_TYPE)) {
            $nodes['expr'] = $this->parser->parseExpression();
        }

        $stream->expect(Token::BLOCK_END_TYPE);

        return new ReturnNode($nodes, [], $token->getLine());
    }

    public function getTag(): string
    {
        return 'return';
    }
}
