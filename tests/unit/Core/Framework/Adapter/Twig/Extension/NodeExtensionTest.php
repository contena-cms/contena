<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Contena\Core\Framework\Adapter\Twig\TemplateFinder;
use Contena\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Contena\Core\Framework\Adapter\Twig\TokenParser\ExtendsTokenParser;
use Contena\Core\Framework\Adapter\Twig\TokenParser\IncludeTokenParser;
use Contena\Core\Framework\Adapter\Twig\TokenParser\ReturnNodeTokenParser;
use Twig\TokenParser\TokenParserInterface;

/**
 * @internal
 */
#[CoversClass(NodeExtension::class)]
class NodeExtensionTest extends TestCase
{
    public function testGetTokenParsers(): void
    {
        $extension = new NodeExtension(
            static::createStub(TemplateFinder::class),
            static::createStub(TemplateScopeDetector::class),
        );
        static::assertCount(3, $extension->getTokenParsers());
        static::assertSame([
            ExtendsTokenParser::class,
            IncludeTokenParser::class,
            ReturnNodeTokenParser::class,
        ], array_map(static fn (TokenParserInterface $parser) => $parser::class, $extension->getTokenParsers()));
    }

    public function testGetFinder(): void
    {
        $finder = $this->createMock(TemplateFinder::class);
        $extension = new NodeExtension(
            $finder,
            static::createStub(TemplateScopeDetector::class),
        );
        static::assertSame($finder, $extension->getFinder());
    }

    public function testEmptyExtensions(): void
    {
        $extension = new NodeExtension(
            static::createStub(TemplateFinder::class),
            static::createStub(TemplateScopeDetector::class),
        );

        static::assertSame([], $extension->getFunctions());
        static::assertSame([], $extension->getFilters());
        static::assertSame([], $extension->getNodeVisitors());
        static::assertSame([[], []], $extension->getOperators());
        static::assertSame([], $extension->getTests());
    }
}
