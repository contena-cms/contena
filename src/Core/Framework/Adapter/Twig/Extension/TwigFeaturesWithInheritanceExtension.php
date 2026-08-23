<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig\Extension;

use Contena\Core\Framework\Adapter\AdapterException;
use Contena\Core\Framework\Adapter\Twig\Node\SwBlockReferenceExpression;
use Contena\Core\Framework\Adapter\Twig\TemplateFinderInterface;
use Contena\Core\Framework\Adapter\Twig\TokenParser\EmbedTokenParser;
use Contena\Core\Framework\Adapter\Twig\TokenParser\FromTokenParser;
use Contena\Core\Framework\Adapter\Twig\TokenParser\ImportTokenParser;
use Contena\Core\Framework\Adapter\Twig\TokenParser\UseTokenParser;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Extension\AbstractExtension;
use Twig\Extension\CoreExtension;
use Twig\Markup;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\BlockReferenceExpression;
use Twig\Node\Node;
use Twig\Parser;
use Twig\TemplateWrapper;
use Twig\TokenParser\TokenParserInterface;
use Twig\TwigFunction;
use Twig\Util\CallableArgumentsExtractor;

/**
 * @internal
 */
class TwigFeaturesWithInheritanceExtension extends AbstractExtension
{
    public function __construct(private readonly TemplateFinderInterface $finder)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('sw_block', null, ['parser_callable' => $this->parseSwBlockFunction(...)]),
            new TwigFunction('sw_source', $this->source(...), ['needs_environment' => true, 'is_safe' => ['all']]),
            new TwigFunction('sw_include', $this->include(...), ['needs_environment' => true, 'needs_context' => true, 'is_safe' => ['all']]),
        ];
    }

    /**
     * @return list<TokenParserInterface>
     */
    public function getTokenParsers(): array
    {
        return [
            new UseTokenParser($this->finder),
            new EmbedTokenParser($this->finder),
            new FromTokenParser($this->finder),
            new ImportTokenParser($this->finder),
        ];
    }

    /**
     * @see CoreExtension::parseBlockFunction
     */
    public function parseSwBlockFunction(Parser $parser, Node $fakeNode, Node $argsNode, int $line): AbstractExpression
    {
        $fakeFunction = new TwigFunction('sw_block', static fn ($name, $template = null) => null);
        $args = new CallableArgumentsExtractor($fakeNode, $fakeFunction)->extractArguments($argsNode);

        // ct-fix-start
        $templateArgument = $args[1] ?? null;

        if ($templateArgument !== null) {
            return new SwBlockReferenceExpression($args[0], $templateArgument, $line);
        }

        if (!$args[0] instanceof AbstractExpression) {
            throw AdapterException::invalidArgument(
                'The first argument of the "sw_block" function must be an instance of AbstractExpression.'
            );
        }
        // ct-fix-end

        return new BlockReferenceExpression($args[0], $templateArgument, $line);
    }

    public function source(Environment $env, string $name, bool $ignoreMissing = false): string
    {
        return CoreExtension::source($env, $this->finder->find($name), $ignoreMissing);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, string>|string|TemplateWrapper $template
     * @param array<string, mixed> $variables
     *
     * @throws LoaderError
     *
     * @see CoreExtension::include
     */
    public function include(
        Environment $env,
        array $context,
        array|string|TemplateWrapper $template,
        array $variables = [],
        bool $withContext = true,
        bool $ignoreMissing = false,
        bool $sandboxed = false
    ): string|Markup {
        // ct-fix-start
        if (\is_array($template)) {
            foreach ($template as &$value) {
                $value = $this->finder->find($value);
            }
        }

        if (\is_string($template)) {
            $template = $this->finder->find($template);
        }
        // ct-fix-end

        return CoreExtension::include($env, $context, $template, $variables, $withContext, $ignoreMissing, $sandboxed);
    }
}
