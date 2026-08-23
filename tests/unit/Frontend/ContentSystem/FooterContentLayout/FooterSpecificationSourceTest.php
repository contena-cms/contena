<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\ContentSystem\FooterContentLayout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignmentEntity;
use Contena\Core\Framework\ContentSystem\Adapter\FactoryHelper\DomainAwareLayoutResolver;
use Contena\Core\Framework\ContentSystem\ContentSection;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Frontend\ContentSystem\FooterContentLayout\FooterContentLayoutCollection;
use Contena\Frontend\ContentSystem\FooterContentLayout\FooterSpecificationSource;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(FooterSpecificationSource::class)]
class FooterSpecificationSourceTest extends TestCase
{
    private DomainAwareLayoutResolver&Stub $resolver;

    private FooterSpecificationSource $source;

    protected function setUp(): void
    {
        $this->resolver = static::createStub(DomainAwareLayoutResolver::class);

        /** @var StaticEntityRepository<FooterContentLayoutCollection> $repository */
        $repository = new StaticEntityRepository([]);

        $this->source = new FooterSpecificationSource(
            $this->resolver,
            $repository,
        );
    }

    #[TestDox('always returns true for supports')]
    public function testSupportsAlwaysReturnsTrue(): void
    {
        $context = Generator::generateChannelContext();

        static::assertTrue($this->source->supports('', new Request(), $context));
    }

    #[TestDox('resolves layout ID from domain-aware assignment')]
    public function testResolveLayoutIdReturnsLayoutIdFromAssignment(): void
    {
        $layoutId = Uuid::randomHex();
        $assignment = static::createStub(AbstractContentLayoutAssignmentEntity::class);
        $assignment->method('getContentLayoutId')->willReturn($layoutId);

        $this->resolver->method('resolve')->willReturn($assignment);

        $context = Generator::generateChannelContext();

        static::assertSame($layoutId, $this->source->resolveLayoutId('', new Request(), $context));
    }

    #[TestDox('returns empty data requirements in specification data')]
    public function testResolveSpecificationDataReturnsEmptyDataRequirements(): void
    {
        $context = Generator::generateChannelContext();
        $result = $this->source->resolveSpecificationData('', new Request(), $context);

        static::assertSame([], $result->dataRequirements);
    }

    #[TestDox('resolves cache tags using footer section tag')]
    public function testResolveCacheTagsUsesFooterSectionTag(): void
    {
        $layoutId = Uuid::randomHex();
        $assignment = static::createStub(AbstractContentLayoutAssignmentEntity::class);
        $assignment->method('getContentLayoutId')->willReturn($layoutId);

        $this->resolver->method('resolve')->willReturn($assignment);

        $context = Generator::generateChannelContext();
        $result = $this->source->resolveCacheTags('', new Request(), $context);

        static::assertSame([ContentSection::FOOTER->buildLayoutTag($layoutId)], $result);
    }

    #[TestDox('throws when resolver returns null')]
    public function testThrowsLayoutAssignmentNotFoundWhenResolverReturnsNull(): void
    {
        $this->resolver->method('resolve')->willReturn(null);

        $context = Generator::generateChannelContext();

        $this->expectExceptionObject(ContentSystemException::layoutAssignmentNotFound(
            'footer',
            '',
            $context->getChannel()->getId()
        ));

        $this->source->resolveLayoutId('', new Request(), $context);
    }
}
