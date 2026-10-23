<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Repository;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class UserTagTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = static::getContainer()->get('user.repository');
    }

    public function testEqualsAnyFilter(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $tag1 = Uuid::randomHex();
        $tag2 = Uuid::randomHex();
        $tag3 = Uuid::randomHex();
        $notAssigned = Uuid::randomHex();

        $this->createUser($id1, [
            ['id' => $tag1, 'name' => 'tag1'],
            ['id' => $tag3, 'name' => 'tag3'],
        ]);

        $this->createUser($id2, [
            ['id' => $tag2, 'name' => 'tag2'],
            ['id' => $tag1, 'name' => 'tag1'],
        ]);

        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('user.tagIds', [$tag1]));
        $ids = $this->repository->searchIds($criteria, $context);

        static::assertContains($id1, $ids->getIds());
        static::assertContains($id2, $ids->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('user.tagIds', [$tag2]));
        $ids = $this->repository->searchIds($criteria, $context);

        static::assertNotContains($id1, $ids->getIds());
        static::assertContains($id2, $ids->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('user.tagIds', [$notAssigned]));
        $ids = $this->repository->searchIds($criteria, $context);

        static::assertNotContains($id1, $ids->getIds());
        static::assertNotContains($id2, $ids->getIds());
    }

    public function testNotEqualsAnyFilter(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $tag1 = Uuid::randomHex();
        $tag2 = Uuid::randomHex();
        $tag3 = Uuid::randomHex();
        $notAssigned = Uuid::randomHex();

        $this->createUser($id1, [
            ['id' => $tag1, 'name' => 'tag1'],
            ['id' => $tag3, 'name' => 'tag3'],
        ]);

        $this->createUser($id2, [
            ['id' => $tag2, 'name' => 'tag2'],
            ['id' => $tag1, 'name' => 'tag1'],
        ]);

        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(
            new NotFilter(NotFilter::CONNECTION_OR, [
                new EqualsAnyFilter('user.tagIds', [$notAssigned]),
            ])
        );
        $ids = $this->repository->searchIds($criteria, $context);

        static::assertContains($id1, $ids->getIds());
        static::assertContains($id2, $ids->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(
            new NotFilter(NotFilter::CONNECTION_OR, [
                new EqualsAnyFilter('user.tagIds', [$tag2]),
            ])
        );
        $ids = $this->repository->searchIds($criteria, $context);

        static::assertContains($id1, $ids->getIds());
        static::assertNotContains($id2, $ids->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(
            new NotFilter(NotFilter::CONNECTION_OR, [
                new EqualsAnyFilter('user.tagIds', [$notAssigned]),
            ])
        );
        $ids = $this->repository->searchIds($criteria, $context);

        static::assertContains($id1, $ids->getIds());
        static::assertContains($id2, $ids->getIds());
    }

    /**
     * @param array<int, array<string, string>> $tags
     */
    private function createUser(string $id, array $tags): void
    {
        $data = [
            'id' => $id,
            'channelId' => TestDefaults::CHANNEL,
            'defaultShippingAddress' => [
                'firstName' => 'not',
                'lastName' => 'not',
                'city' => 'not',
                'street' => 'not',
                'zipcode' => 'not',
                'salutationId' => $this->getValidSalutationId(),
                'country' => ['name' => 'not'],
            ],
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'lastName' => 'not',
            'firstName' => 'not',
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => 'not',
            'tags' => $tags,
        ];

        $context = Context::createDefaultContext();

        $this->repository->create([$data], $context);
    }
}
