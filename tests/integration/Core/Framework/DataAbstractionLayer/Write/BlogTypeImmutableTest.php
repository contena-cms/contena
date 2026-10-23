<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Write;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
class BlogTypeImmutableTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testUpdatingBlogTypeToDifferentValueFails(): void
    {
        $id = Uuid::randomHex();
        $this->createBlog($id, 'default');

        $exception = new WriteException();

        $violationList = new ConstraintViolationList();
        $violationList->add(
            new ConstraintViolation(
                'The field "type" of "blog" is immutable and cannot be updated.',
                'The field "type" of "blog" is immutable and cannot be updated.',
                [
                    'field' => 'type',
                    'entity' => 'blog',
                ],
                'special',
                'type',
                'alternative'
            )
        );

        $exception->add(new WriteConstraintViolationException($violationList));
        static::expectExceptionObject($exception);

        try {
            $this->getRepository()->update([
                ['id' => $id, 'type' => 'alternative'],
            ], Context::createDefaultContext());
        } finally {
            $this->verifyBlogAfterUpdate($id, 'default', 'Test blog', 1);
        }
    }

    public function testUpdatingBlogTypeWithSameValueSucceeds(): void
    {
        $id = Uuid::randomHex();
        $this->createBlog($id, 'special');

        $event = $this->getRepository()->update([
            ['id' => $id, 'name' => 'Test blog updated', 'stock' => 2, 'type' => 'special'],
        ], Context::createDefaultContext());

        static::assertCount(0, $event->getErrors());
        static::assertSame([
            $id,
        ], $event->getEventByEntityName(BlogDefinition::ENTITY_NAME)?->getIds());

        $this->verifyBlogAfterUpdate($id, 'special', 'Test blog updated', 2);
    }

    public function testUpsertBlogTypeManyTimesWithSameValueSucceeds(): void
    {
        $id = Uuid::randomHex();

        $event = $this->getRepository()->upsert([
            [
                'id' => $id,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test blog',
                'type' => 'special',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 19],
            ],
        ], Context::createDefaultContext());

        static::assertCount(0, $event->getErrors());
        static::assertSame([
            $id,
        ], $event->getEventByEntityName(BlogDefinition::ENTITY_NAME)?->getIds());

        $event = $this->getRepository()->upsert([
            [
                'id' => $id,
                'name' => 'Test blog updated',
                'stock' => 2,
                'type' => 'special', // same type as before
            ],
        ], Context::createDefaultContext());

        static::assertCount(0, $event->getErrors());
        static::assertSame([
            $id,
        ], $event->getEventByEntityName(BlogDefinition::ENTITY_NAME)?->getIds());

        $this->verifyBlogAfterUpdate($id, 'special', 'Test blog updated', 2);
    }

    public function testUpsertBlogTypeManyTimesWithDifferentValueFails(): void
    {
        $id = Uuid::randomHex();

        $event = $this->getRepository()->upsert([
            [
                'id' => $id,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test blog',
                'type' => 'special',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 19],
            ],
        ], Context::createDefaultContext());

        static::assertCount(0, $event->getErrors());
        static::assertSame([
            $id,
        ], $event->getEventByEntityName(BlogDefinition::ENTITY_NAME)?->getIds());

        $exception = new WriteException();

        $violationList = new ConstraintViolationList();
        $violationList->add(
            new ConstraintViolation(
                'The field "type" of "blog" is immutable and cannot be updated.',
                'The field "type" of "blog" is immutable and cannot be updated.',
                [
                    'field' => 'type',
                    'entity' => 'blog',
                ],
                'special',
                'type',
                'not so special'
            )
        );

        $exception->add(new WriteConstraintViolationException($violationList));
        static::expectExceptionObject($exception);

        try {
            $this->getRepository()->upsert([
                [
                    'id' => $id,
                    'name' => 'Test blog updated',
                    'type' => 'not so special', // different type than before
                ],
            ], Context::createDefaultContext());
        } finally {
            $this->verifyBlogAfterUpdate($id, 'special', 'Test blog', 1);
        }
    }

    private function createBlog(string $id, string $type): void
    {
        $this->getRepository()->create([
            [
                'id' => $id,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test blog',
                'type' => $type,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 19],
            ],
        ], Context::createDefaultContext());
    }

    /**
     * @return EntityRepository<BlogCollection>
     */
    private function getRepository(): EntityRepository
    {
        /** @var EntityRepository<BlogCollection> $repo */
        $repo = static::getContainer()->get('blog.repository');

        return $repo;
    }

    private function verifyBlogAfterUpdate(string $id, string $expectedType, string $expectedName, int $expectedStock): void
    {
        $blog = $this->getRepository()->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertSame($expectedType, $blog->getType());
        static::assertSame($expectedName, $blog->getName());
        static::assertSame($expectedStock, $blog->getStock());
    }
}
