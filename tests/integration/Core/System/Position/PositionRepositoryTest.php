<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Position;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Position\PositionCollection;
use Contena\Core\System\Position\PositionEntity;
use Contena\Core\System\User\UserCollection;

/**
 * @internal
 */
class PositionRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<PositionCollection>
     */
    private EntityRepository $positionRepository;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->positionRepository = static::getContainer()->get('position.repository');
        $this->userRepository = static::getContainer()->get('user.repository');
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testPersistsTranslationsCustomFieldsAndMultipleUserAssignments(): void
    {
        $firstPositionId = Uuid::randomHex();
        $secondPositionId = Uuid::randomHex();
        $userId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->positionRepository->create([
            [
                'id' => $firstPositionId,
                'code' => 'integration-first-' . $firstPositionId,
                'name' => '测试岗位一',
                'description' => '第一个测试岗位',
                'customFields' => ['responsibility' => 'company'],
                'position' => 10,
                'active' => true,
            ],
            [
                'id' => $secondPositionId,
                'code' => 'integration-second-' . $secondPositionId,
                'name' => '测试岗位二',
                'position' => 20,
                'active' => true,
            ],
        ], $context);

        $localeId = $this->connection->fetchOne('SELECT LOWER(HEX(`id`)) FROM `locale` LIMIT 1');
        static::assertIsString($localeId);

        $this->userRepository->create([[
            'id' => $userId,
            'localeId' => $localeId,
            'username' => 'position-test-' . $userId,
            'password' => 'integration-test-password',
            'name' => 'Position Test',
            'email' => $userId . '@example.invalid',
            'active' => true,
            'positions' => [
                ['id' => $firstPositionId],
                ['id' => $secondPositionId],
            ],
        ]], $context);

        $position = $this->positionRepository->search(
            new Criteria([$firstPositionId]),
            $context
        )->getEntities()->first();
        static::assertInstanceOf(PositionEntity::class, $position);
        static::assertSame('测试岗位一', $position->getName());
        static::assertSame('第一个测试岗位', $position->getDescription());
        static::assertSame(['responsibility' => 'company'], $position->getCustomFields());
        static::assertEqualsCanonicalizing(
            [$firstPositionId, $secondPositionId],
            $this->connection->fetchFirstColumn(
                'SELECT LOWER(HEX(`position_id`)) FROM `user_position` WHERE `user_id` = UNHEX(:userId)',
                ['userId' => $userId]
            )
        );
    }
}
