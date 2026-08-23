<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\ContentSystem\HeaderContentLayout;

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
use Contena\Frontend\ContentSystem\HeaderContentLayout\HeaderContentLayoutCollection;
use Contena\Frontend\ContentSystem\HeaderContentLayout\HeaderSpecificationSource;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(HeaderSpecificationSource::class)]
class HeaderSpecificationSourceTest extends TestCase
{
    private DomainAwareLayoutResolver&Stub $resolver;

    private HeaderSpecificationSource $source;

    protected function setUp(): void
    {
        $this->resolver = static::createStub(DomainAwareLayoutResolver::class);

        /** @var StaticEntityRepository<HeaderContentLayoutCollection> $repository */
        $repository = new StaticEntityRepository([]);

        $this->source = new HeaderSpecificationSource(
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

    #[TestDox('resolves cache tags using header section tag')]
    public function testResolveCacheTagsUsesHeaderSectionTag(): void
    {
        $layoutId = Uuid::randomHex();
        $assignment = static::createStub(AbstractContentLayoutAssignmentEntity::class);
        $assignment->method('getContentLayoutId')->willReturn($layoutId);

        $this->resolver->method('resolve')->willReturn($assignment);

        $context = Generator::generateChannelContext();
        $result = $this->source->resolveCacheTags('', new Request(), $context);

        static::assertSame([ContentSection::HEADER->buildLayoutTag($layoutId)], $result);
    }

    #[TestDox('throws when resolver returns null')]
    public function testThrowsLayoutAssignmentNotFoundWhenResolverReturnsNull(): void
    {
        $this->resolver->method('resolve')->willReturn(null);

        $context = Generator::generateChannelContext();

        $this->expectExceptionObject(ContentSystemException::layoutAssignmentNotFound(
            'header',
            '',
            $context->getChannel()->getId()
        ));

        $this->source->resolveLayoutId('', new Request(), $context);
    }

    #[TestDox('does not support entity-type resolution by default')]
    public function testSupportsEntityTypeDefaultsToFalse(): void
    {
        static::assertFalse($this->source->supportsEntityType('blog'));
    }

    #[TestDox('throws when entity specification data is resolved on a source that does not support entity types')]
    public function testResolveSpecificationDataForEntityThrowsWhenEntityTypeUnsupported(): void
    {
        $context = Generator::generateChannelContext();

        $this->expectExceptionObject(ContentSystemException::entityTypeResolutionUnsupported());

        $this->source->resolveSpecificationDataForEntity('blog-1', new Request(), $context);
    }
}
