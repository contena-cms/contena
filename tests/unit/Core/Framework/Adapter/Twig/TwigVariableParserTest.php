<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Twig\TwigVariableParser;
use Contena\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(TwigVariableParser::class)]
class TwigVariableParserTest extends TestCase
{
    public function testParser(): void
    {
        $template = <<<TWIG
{% if blog.price.gross == null %}
    {{ dump(blog.prices) }}
{% endif %}

{{ blog.name|striptags(blog.stock) }}

{% set temp = blog.translated.name %}

{% set temp = blog.manufacturer.cover.id %}

{% include 'content.html.twig' with {'foo': 'bar', 'media': blog.media} %}

TWIG;

        $twig = new Environment(new ArrayLoader([]));
        $twig->addExtension(new DebugExtension());
        $parser = new TwigVariableParser($twig);

        $variables = $parser->parse($template);

        $expected = [
            'blog.price.gross',
            'blog.prices',
            'blog.name',
            'blog.stock',
            'blog.translated.name',
            'blog.manufacturer.cover.id',
            'blog.media',
        ];

        sort($expected);
        sort($variables);

        static::assertSame($expected, $variables);
    }

    public function testParserHandlesAssociationsInLoops(): void
    {
        $template = <<<TWIG
{{ blog.name|striptags(blog.stock) }}

{% for option in blog.options %}
    {{ option.group.name }}
{% endfor %}

{% for options in blog.options %}
    {{ options.group.name }}
{% endfor %}

{% for option in blog.options %}
    {{ foo.group.name }}
{% endfor %}

{% for foo in blog.foo|sort((a, b) => a.position <=> b.position) %}
    {{ foo.bar.baz }}
{% endfor %}

{% set foo = blog.test %}
{% for bar in foo %}
    {% set baz = bar.shop %}
    {{ baz.ware }}
{% endfor %}

TWIG;

        $parser = new TwigVariableParser(new Environment(new ArrayLoader([])));

        $variables = $parser->parse($template);

        $expected = [
            'foo.group.name',
            'blog.name',
            'blog.options',
            'blog.options.group.name',
            'blog.stock',
            'blog.foo',
            'blog.foo.bar.baz',
            'blog.test',
            'blog.test.shop',
            'blog.test.shop.ware',
        ];

        sort($expected);
        sort($variables);

        static::assertSame($expected, $variables);
    }
}
