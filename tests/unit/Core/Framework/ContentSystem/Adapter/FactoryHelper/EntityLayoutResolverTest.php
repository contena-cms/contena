<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Adapter\FactoryHelper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutResolver;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\ContentSystem\HeaderContentLayout\HeaderContentLayoutCollection;
use Contena\Frontend\ContentSystem\HeaderContentLayout\HeaderContentLayoutEntity;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(EntityLayoutResolver::class)]
class EntityLayoutResolverTest extends TestCase
{
    private EntityLayoutResolver $resolver;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->resolver = new EntityLayoutResolver();
    }

    #[TestDox('builds placeholder values from entity id field and scalar query parameters, ignoring non-scalar parameters')]
    public function testResolvePlaceholdersMergesEntityIdWithScalarQueryParameters(): void
    {
        $request = new Request(['color' => 'red', 'tags' => ['a', 'b']]);

        $result = $this->resolver->resolvePlaceholders('blogId', 'blog-id-1', $request);

        static::assertSame(
            ['blogId' => 'blog-id-1', 'color' => 'red'],
            $result->all()
        );
    }

    #[TestDox('returns layout ID when assignment exists')]
    public function testReturnsLayoutIdWhenAssignmentExists(): void
    {
        $layoutId = $this->ids->get('layout');
        $entity = $this->createAssignmentEntity($layoutId);

        $repository = $this->createRepository($entity);

        $context = Generator::generateChannelContext();

        $result = $this->resolver->findLayoutId('blogId', $this->ids->get('blog'), $context, $repository);

        static::assertSame($layoutId, $result);
    }

    #[TestDox('returns null when no assignment exists')]
    public function testReturnsNullFromLayoutIdLookupWhenNoAssignment(): void
    {
        $repository = $this->createRepository();
        $context = Generator::generateChannelContext();

        $result = $this->resolver->findLayoutId('blogId', $this->ids->get('blog'), $context, $repository);

        static::assertNull($result);
    }

    /**
     * @return StaticEntityRepository<HeaderContentLayoutCollection>
     */
    private function createRepository(HeaderContentLayoutEntity ...$entities): StaticEntityRepository
    {
        /** @var StaticEntityRepository<HeaderContentLayoutCollection> $repository */
        $repository = new StaticEntityRepository([$entities]);

        return $repository;
    }

    private function createAssignmentEntity(string $layoutId): HeaderContentLayoutEntity
    {
        $entity = new HeaderContentLayoutEntity();
        $entity->setId($this->ids->get('assignment'));
        $entity->setContentLayoutId($layoutId);

        return $entity;
    }
}
