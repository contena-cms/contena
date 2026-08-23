<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Repository;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class BlogRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $repository;

    private Context $context;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('blog.repository');
        $this->context = Context::createDefaultContext();
    }

    #[DataProvider('blogTypes')]
    public function testWriteBlogType(?string $type, string $expectedType): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'Default name',
            'type' => $type,
        ];

        if ($type === 'unset') {
            unset($data['type']);
        }

        $this->repository->create([$data], $this->context);

        $blog = $this->repository->search(new Criteria([$id]), $this->context)->getEntities()->get($id);
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertSame($expectedType, $blog->getType());
    }

    public static function blogTypes(): \Generator
    {
        yield 'no type provided' => ['unset', BlogDefinition::TYPE_POST];
        yield 'default Blog type provided' => [BlogDefinition::TYPE_POST, BlogDefinition::TYPE_POST];
        yield 'media Blog type provided' => [BlogDefinition::TYPE_MEDIA, BlogDefinition::TYPE_MEDIA];
    }
}
