<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Twig\TemplateIterator;
use Contena\Core\Framework\Bundle;
use Symfony\Bundle\TwigBundle\TemplateIterator as SymfonyTemplateIterator;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
#[CoversClass(TemplateIterator::class)]
class TemplateIteratorTest extends TestCase
{
    private const string FIXTURE_BUNDLE_NAME = 'TemplateIteratorContenaFixture';

    private TemplateIterator $iterator;

    protected function setUp(): void
    {
        $fixtureBundlePath = __DIR__ . '/_fixtures/template-iterator/TemplateIteratorContenaFixtureBundle';
        $fixtureBundle = new TemplateIteratorContenaFixtureBundle($fixtureBundlePath);

        $kernel = static::createStub(KernelInterface::class);
        $kernel
            ->method('getBundles')
            ->willReturn([self::FIXTURE_BUNDLE_NAME => $fixtureBundle]);

        $this->iterator = new TemplateIterator(
            new SymfonyTemplateIterator($kernel, namePatterns: ['*.twig']),
            [self::FIXTURE_BUNDLE_NAME => TemplateIteratorContenaFixtureBundle::class],
            [self::FIXTURE_BUNDLE_NAME => ['path' => $fixtureBundlePath]],
        );
    }

    public function testIteratorStripsContenaBundleNamespacePrefix(): void
    {
        $templateList = iterator_to_array($this->iterator, false);

        static::assertContains('files/agentic/llms.txt.twig', $templateList);

        foreach ($templateList as $template) {
            static::assertStringNotContainsString('@' . self::FIXTURE_BUNDLE_NAME . '/', $template);
        }
    }

    public function testIteratorKeepsSymfonyDefaultDotFileBehavior(): void
    {
        $templateList = iterator_to_array($this->iterator, false);

        static::assertContains('files/agentic/llms.txt.twig', $templateList);

        foreach ($templateList as $template) {
            static::assertStringNotContainsString('/.', $template);
        }
    }

    public function testFilteredLookupIncludesHiddenTemplatePathsWhenRequested(): void
    {
        $templateList = iterator_to_array($this->iterator->getTemplatePathsForSubPath('files/agentic', true), false);
        sort($templateList);

        static::assertSame([
            'files/agentic/.well-known/ucp.json.twig',
            'files/agentic/llms.txt.twig',
        ], $templateList);
    }

    public function testFilteredLookupCanKeepDefaultDotFileBehavior(): void
    {
        $templateList = iterator_to_array($this->iterator->getTemplatePathsForSubPath('files/agentic'), false);

        static::assertSame(['files/agentic/llms.txt.twig'], $templateList);
    }
}

/**
 * @internal
 */
final class TemplateIteratorContenaFixtureBundle extends Bundle
{
    public function __construct(private readonly string $fixturePath)
    {
    }

    public function getPath(): string
    {
        return $this->fixturePath;
    }
}
