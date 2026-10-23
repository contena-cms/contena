<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\BlogStream\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\BlogStream\BlogStreamCollection;
use Contena\Core\Content\BlogStream\BlogStreamDefinition;
use Contena\Core\Content\BlogStream\BlogStreamEntity;
use Contena\Core\Content\BlogStream\Exception\EmptyBlogStreamException;
use Contena\Core\Content\BlogStream\Exception\NoFilterException;
use Contena\Core\Content\BlogStream\Service\BlogStreamBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Feature\FeatureException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(BlogStreamBuilder::class)]
class BlogStreamBuilderTest extends TestCase
{
    /**
     * buildFilters() is the deprecated backward-compatible fallback. It carries the deprecation itself,
     * so under the v6.8.0.0 flag it throws — that is the hard-fail that forces interface-only builders
     * (which still route through this method) to migrate to AbstractBlogStreamBuilder::enrichCriteria().
     */
    public function testBuildFiltersThrowsWhenV68IsActive(): void
    {
        /** @var EntityRepository<BlogStreamCollection>&MockObject $repository */
        $repository = static::createStub(EntityRepository::class);
        $builder = new BlogStreamBuilder($repository, static::createStub(EntityDefinition::class));

        $this->expectException(FeatureException::class);

        $builder->buildFilters('stream-id', Context::createDefaultContext());
    }

    /**
     * Before v6.8.0.0 the deprecated buildFilters() stays callable: it only triggers a non-fatal deprecation
     * (the emitted message is asserted in the integration test). It must not throw the FeatureException here —
     * it proceeds past the deprecation gate and fails only because the stub stream does not resolve.
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testBuildFiltersDoesNotThrowWhenV68IsInactive(): void
    {
        $repository = new StaticEntityRepository([new BlogStreamCollection([])]);
        $builder = new BlogStreamBuilder($repository, static::createStub(EntityDefinition::class));

        $this->expectException(EntityNotFoundException::class);

        $builder->buildFilters('stream-id', Context::createDefaultContext());
    }

    public function testEnrichCriteriaThrowsEmptyBlogStreamExceptionForValidStreamWithoutFilters(): void
    {
        $streamId = Uuid::randomHex();
        $stream = new BlogStreamEntity();
        $stream->setId($streamId);
        $stream->setUniqueIdentifier($streamId);
        $stream->setApiFilter([]);
        $stream->setInvalid(false);

        $repository = new StaticEntityRepository([new BlogStreamCollection([$stream])], new BlogStreamDefinition());
        $builder = new BlogStreamBuilder($repository, static::createStub(EntityDefinition::class));

        $this->expectException(EmptyBlogStreamException::class);

        $builder->enrichCriteria(new Criteria(), $streamId, Context::createDefaultContext());
    }

    /**
     * A broken/invalid stream must throw the plain NoFilterException, not the EmptyBlogStreamException subtype.
     */
    public function testEnrichCriteriaThrowsNoFilterExceptionForInvalidStream(): void
    {
        $streamId = Uuid::randomHex();
        $stream = new BlogStreamEntity();
        $stream->setId($streamId);
        $stream->setUniqueIdentifier($streamId);
        $stream->setApiFilter(null);
        $stream->setInvalid(true);

        $repository = new StaticEntityRepository([new BlogStreamCollection([$stream])], new BlogStreamDefinition());
        $builder = new BlogStreamBuilder($repository, static::createStub(EntityDefinition::class));

        try {
            $builder->enrichCriteria(new Criteria(), $streamId, Context::createDefaultContext());
            static::fail('Expected NoFilterException to be thrown');
        } catch (NoFilterException $exception) {
            static::assertNotInstanceOf(EmptyBlogStreamException::class, $exception);
        }
    }
}
