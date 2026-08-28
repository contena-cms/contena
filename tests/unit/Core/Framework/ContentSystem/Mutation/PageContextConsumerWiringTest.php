<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Mutation;

use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Contena\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Contena\Core\Framework\ContentSystem\Mutation\PageContextConsumerWiring;
use Contena\Core\Framework\ContentSystem\Resolution\CandidateOrigin;
use Contena\Core\Framework\ContentSystem\Resolution\PropertyKind;
use Contena\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Contena\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Contena\Core\Framework\ContentSystem\Resolution\ResolutionCandidate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PageContextConsumerWiring::class)]
class PageContextConsumerWiringTest extends TestCase
{
    private const BLOG_FQCN = 'Contena\\Core\\Content\\Blog\\BlogEntity';

    #[TestDox('seeds an unresolved reference from the matching provided context')]
    public function testSeedsUnresolvedReference(): void
    {
        $article = new ContentElement('a1', 'CT:Blog:Card');

        (new PageContextConsumerWiring())->apply(
            [$article],
            ['a1' => [$this->reference('blog', self::BLOG_FQCN, true)]],
            [$this->blogContext()],
        );

        $consumer = $article->getAcceptsContext()['blog'] ?? null;
        static::assertInstanceOf(ContextConsumer::class, $consumer);
        static::assertSame(ContextType::Single, $consumer->type);
        static::assertTrue($consumer->required);
        static::assertFalse($consumer->redistribute);
    }

    #[TestDox('relays redistribute up every ancestor to the root')]
    public function testRelaysUpAncestors(): void
    {
        $article = new ContentElement('article', 'CT:Blog:Card');
        $inner = new ContentElement('inner', 'CT:Grid:Container', [], [], ['content' => new SlotContent([$article])]);
        $outer = new ContentElement('outer', 'CT:Grid:Container', [], [], ['content' => new SlotContent([$inner])]);

        (new PageContextConsumerWiring())->apply(
            [$outer],
            ['article' => [$this->reference('blog', self::BLOG_FQCN)]],
            [$this->blogContext()],
        );

        static::assertFalse($article->getAcceptsContext()['blog']->redistribute);

        foreach ([$inner, $outer] as $ancestor) {
            $relay = $ancestor->getAcceptsContext()['blog'] ?? null;
            static::assertInstanceOf(ContextConsumer::class, $relay);
            static::assertTrue($relay->redistribute);
            static::assertFalse($relay->required);
        }
    }

    #[TestDox('wires a parent-resolved reference from its resolved binding')]
    public function testWiresParentResolvedReference(): void
    {
        $article = new ContentElement('a1', 'CT:Blog:Card');
        $resolved = new ResolutionCandidate(
            CandidateOrigin::Parent,
            'blog',
            '__page_context_root__',
            null,
            DistributionStrategy::Broadcast,
            ContextType::Single,
        );

        (new PageContextConsumerWiring())->apply(
            [$article],
            ['a1' => [$this->reference('blog', self::BLOG_FQCN, false, $resolved)]],
            [],
        );

        static::assertArrayHasKey('blog', $article->getAcceptsContext());
    }

    #[TestDox('ignores a same-type reference whose name does not match the provided context key')]
    public function testIgnoresNameMismatch(): void
    {
        $element = new ContentElement('e1', 'CT:Test');

        (new PageContextConsumerWiring())->apply(
            [$element],
            ['e1' => [$this->reference('relatedBlog', self::BLOG_FQCN)]],
            [$this->blogContext()],
        );

        static::assertSame([], $element->getAcceptsContext());
    }

    #[TestDox('leaves a reference filled by stored wiring untouched')]
    public function testIgnoresSelfFilledReference(): void
    {
        $element = new ContentElement('e1', 'CT:Test');
        $stored = new ResolutionCandidate(CandidateOrigin::Stored);

        (new PageContextConsumerWiring())->apply(
            [$element],
            ['e1' => [$this->reference('blog', self::BLOG_FQCN, false, $stored)]],
            [$this->blogContext()],
        );

        static::assertSame([], $element->getAcceptsContext());
    }

    #[TestDox('never overrides an authored consumer')]
    public function testNeverOverridesAuthoredConsumer(): void
    {
        $authored = new ContextConsumer(ContextType::Single, true, false, null, 'item');
        $article = new ContentElement(
            'a1',
            'CT:Blog:Card',
            [],
            [],
            [],
            new ContextDefinitions([], ['blog' => $authored]),
        );

        (new PageContextConsumerWiring())->apply(
            [$article],
            ['a1' => [$this->reference('blog', self::BLOG_FQCN)]],
            [$this->blogContext()],
        );

        static::assertSame($authored, $article->getAcceptsContext()['blog']);
    }

    private function reference(string $key, string $fqcn, bool $required = false, ?ResolutionCandidate $resolved = null): PropertyResolution
    {
        return new PropertyResolution($key, PropertyKind::Reference, $required, null, null, $fqcn, $resolved);
    }

    private function blogContext(): ProvidedContext
    {
        return new ProvidedContext('blog', self::BLOG_FQCN, ContextType::Single, '__page_context_root__', DistributionStrategy::Broadcast);
    }
}
