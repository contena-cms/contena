<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig\TokenParser;

use Contena\Core\Framework\Adapter\Twig\TemplateFinderInterface;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\Variable\AssignTemplateVariable;
use Twig\Node\Expression\Variable\TemplateVariable;
use Twig\Node\ImportNode;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * @internal
 *
 * @see \Twig\TokenParser\ImportTokenParser
 */
final class ImportTokenParser extends AbstractTokenParser
{
    public function __construct(private readonly TemplateFinderInterface $templateFinder)
    {
    }

    public function parse(Token $token): Node
    {
        $macro = $this->parser->parseExpression();
        $this->parser->getStream()->expect(Token::NAME_TYPE, 'as');
        $name = $this->parser->getStream()->expect(Token::NAME_TYPE)->getValue();
        $var = new AssignTemplateVariable(new TemplateVariable($name, $token->getLine()), $this->parser->isMainScope());
        $this->parser->getStream()->expect(Token::BLOCK_END_TYPE);
        $this->parser->addImportedSymbol('template', $name);

        // ct-fix-start
        if ($macro instanceof ConstantExpression) {
            $macro->setAttribute('value', $this->templateFinder->find($macro->getAttribute('value')));
        }
        // ct-fix-end

        return new ImportNode($macro, $var, $token->getLine());
    }

    public function getTag(): string
    {
        return 'sw_import';
    }
}
