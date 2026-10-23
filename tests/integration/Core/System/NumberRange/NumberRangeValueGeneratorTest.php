<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\NumberRange;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeCollection;
use Contena\Core\System\NumberRange\NumberRangeCollection;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\System\NumberRange\ValueGenerator\NumberRangeValueGenerator;
use Contena\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Contena\Core\Test\TestDefaults;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class NumberRangeValueGeneratorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Context $context;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->setupDatabase();
        $this->context = Context::createDefaultContext();
    }

    public function testGetConfiguration(): void
    {
        /** @var NumberRangeValueGenerator $realGenerator */
        $realGenerator = static::getContainer()->get(NumberRangeValueGeneratorInterface::class);
        $value = $realGenerator->getValue('blog', $this->context, Defaults::CHANNEL_TYPE_FRONTEND);
        static::assertSame('SW10000', $value);
        $value = $realGenerator->getValue('blog', $this->context, Defaults::CHANNEL_TYPE_FRONTEND);
        static::assertSame('SW10001', $value);
        $value = $realGenerator->getValue('blog', $this->context, Defaults::CHANNEL_TYPE_FRONTEND);
        static::assertSame('SW10002', $value);

        $value = $realGenerator->getValue('order', $this->context, Defaults::CHANNEL_TYPE_FRONTEND);
        static::assertSame('10000', $value);
        $value = $realGenerator->getValue('order', $this->context, Defaults::CHANNEL_TYPE_FRONTEND);
        static::assertSame('10001', $value);
        $value = $realGenerator->getValue('order', $this->context, Defaults::CHANNEL_TYPE_FRONTEND);
        static::assertSame('10002', $value);

        $value = $realGenerator->getValue('user', $this->context, Defaults::CHANNEL_TYPE_FRONTEND);
        static::assertSame('10000', $value);
        $value = $realGenerator->getValue('user', $this->context, Defaults::CHANNEL_TYPE_FRONTEND);
        static::assertSame('10001', $value);
        $value = $realGenerator->getValue('user', $this->context, Defaults::CHANNEL_TYPE_FRONTEND);
        static::assertSame('10002', $value);
    }

    public function testIncreaseStartNumberInConfiguration(): void
    {
        /** @var NumberRangeValueGenerator $realGenerator */
        $realGenerator = static::getContainer()->get(NumberRangeValueGeneratorInterface::class);

        $value = $realGenerator->getValue('order', $this->context, Defaults::CHANNEL_TYPE_FRONTEND);
        static::assertSame('10000', $value);

        /** @var EntityRepository<NumberRangeTypeCollection> $numberRange */
        $numberRange = static::getContainer()->get('number_range_type.repository');
        $search = $numberRange->search((new Criteria())->addFilter(new EqualsFilter('technicalName', 'order')), $this->context)
            ->getEntities()
            ->first();

        static::assertNotNull($search);
        $typeId = $search->getId();

        /** @var EntityRepository<NumberRangeCollection> $numberRange */
        $numberRange = static::getContainer()->get('number_range.repository');

        $search = $numberRange->search((new Criteria())->addFilter(new EqualsFilter('typeId', $typeId)), $this->context)
            ->getEntities()
            ->first();

        static::assertNotNull($search);

        static::getContainer()->get('number_range.repository')->update([[
            'id' => $search->getId(),
            'start' => 20000,
        ]], $this->context);

        $value = $realGenerator->getValue('order', $this->context, Defaults::CHANNEL_TYPE_FRONTEND);
        static::assertSame('20000', $value);
    }

    public function testGetValueStartingFromZero(): void
    {
        /** @var NumberRangeValueGenerator $realGenerator */
        $realGenerator = static::getContainer()->get(NumberRangeValueGeneratorInterface::class);

        /** @var EntityRepository<NumberRangeCollection> $numberRange */
        $numberRange = static::getContainer()->get('number_range.repository');

        $search = $numberRange->search((new Criteria())->addFilter(new EqualsFilter('type.technicalName', 'order')), $this->context)
            ->getEntities()
            ->first();

        static::assertNotNull($search);

        static::getContainer()->get('number_range.repository')->update([[
            'id' => $search->getId(),
            'start' => 0,
        ]], $this->context);

        $value = $realGenerator->getValue('order', $this->context, Defaults::CHANNEL_TYPE_FRONTEND);
        static::assertSame('0', $value);
    }

    public function testPreviewPatternByNumberRangeIdUsesConcreteNumberRangeState(): void
    {
        /** @var AbstractNumberRangeValueGenerator $realGenerator */
        $realGenerator = static::getContainer()->get(AbstractNumberRangeValueGenerator::class);

        $userTypeId = $this->getNumberRangeTypeId('user');
        $globalNumberRangeId = $this->getGlobalNumberRangeId($userTypeId);
        $channelNumberRangeId = Uuid::randomHex();

        $this->createChannelNumberRange($channelNumberRangeId, $userTypeId);
        $this->setNumberRangeState($globalNumberRangeId, 10000);
        $this->setNumberRangeState($channelNumberRangeId, 10000);

        static::assertSame('10001', $realGenerator->getValue('user', $this->context, TestDefaults::CHANNEL));
        static::assertSame('10002', $realGenerator->previewPatternByNumberRangeId($channelNumberRangeId, '{n}', 0));
    }

    public function testDeprecatedPreviewPatternByTypeStillUsesGlobalNumberRangeState(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        /** @var AbstractNumberRangeValueGenerator $realGenerator */
        $realGenerator = static::getContainer()->get(AbstractNumberRangeValueGenerator::class);

        $userTypeId = $this->getNumberRangeTypeId('user');
        $globalNumberRangeId = $this->getGlobalNumberRangeId($userTypeId);
        $channelNumberRangeId = Uuid::randomHex();

        $this->createChannelNumberRange($channelNumberRangeId, $userTypeId);
        $this->setNumberRangeState($globalNumberRangeId, 10000);
        $this->setNumberRangeState($channelNumberRangeId, 10000);

        static::assertSame('10001', $realGenerator->getValue('user', $this->context, TestDefaults::CHANNEL));
        static::assertSame('10001', $realGenerator->previewPattern('user', '{n}', 0));
    }

    private function setupDatabase(): void
    {
        $sql = <<<'SQL'
            DELETE FROM `number_range_state`;
SQL;
        $this->connection->executeStatement($sql);
    }

    private function getNumberRangeTypeId(string $technicalName): string
    {
        $typeId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `number_range_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => $technicalName]
        );

        static::assertIsString($typeId);

        return $typeId;
    }

    private function getGlobalNumberRangeId(string $typeId): string
    {
        $numberRangeId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `number_range` WHERE `type_id` = :typeId AND `global` = 1',
            ['typeId' => Uuid::fromHexToBytes($typeId)]
        );

        static::assertIsString($numberRangeId);

        return $numberRangeId;
    }

    private function createChannelNumberRange(string $numberRangeId, string $typeId): void
    {
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('number_range', [
            'id' => Uuid::fromHexToBytes($numberRangeId),
            'type_id' => Uuid::fromHexToBytes($typeId),
            'global' => 0,
            'pattern' => '{n}',
            'start' => 10000,
            'created_at' => $createdAt,
        ]);

        $this->connection->insert('number_range_translation', [
            'number_range_id' => Uuid::fromHexToBytes($numberRangeId),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'name' => 'Sales channel users',
            'created_at' => $createdAt,
        ]);

        $this->connection->insert('number_range_channel', [
            'id' => Uuid::randomBytes(),
            'number_range_id' => Uuid::fromHexToBytes($numberRangeId),
            'channel_id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL),
            'number_range_type_id' => Uuid::fromHexToBytes($typeId),
            'created_at' => $createdAt,
        ]);
    }

    private function setNumberRangeState(string $numberRangeId, int $lastValue): void
    {
        $this->connection->executeStatement('
            INSERT INTO `number_range_state` (`id`, `number_range_id`, `last_value`, `created_at`)
            VALUES (:id, :numberRangeId, :lastValue, :createdAt)
        ', [
            'id' => Uuid::randomBytes(),
            'numberRangeId' => Uuid::fromHexToBytes($numberRangeId),
            'lastValue' => $lastValue,
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
