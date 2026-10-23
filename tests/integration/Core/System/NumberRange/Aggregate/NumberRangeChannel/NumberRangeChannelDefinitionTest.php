<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\NumberRange\Aggregate\NumberRangeChannel;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeChannel\NumberRangeChannelCollection;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeChannel\NumberRangeChannelEntity;
use Contena\Core\System\NumberRange\NumberRangeCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class NumberRangeChannelDefinitionTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<NumberRangeCollection>
     */
    private EntityRepository $numberRangeRepository;

    /**
     * @var EntityRepository<ChannelCollection>
     */
    private EntityRepository $channelRepository;

    protected function setUp(): void
    {
        $this->numberRangeRepository = static::getContainer()->get('number_range.repository');
        $this->channelRepository = static::getContainer()->get('channel.repository');
    }

    public function testNumberRangeChannelCollectionFromNumberRange(): void
    {
        $numberRangeId = $this->createNumberRange();

        $criteria = new Criteria([$numberRangeId]);
        $criteria->addAssociation('numberRangeChannels');

        $numberRange = $this->numberRangeRepository->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($numberRange);
        $this->assertNumberRangeChannel($numberRangeId, $numberRange->getNumberRangeChannels());
    }

    public function testNumberRangeChannelCollectionFromChannel(): void
    {
        $numberRangeId = $this->createNumberRange();

        $criteria = new Criteria([TestDefaults::CHANNEL]);
        $criteria->addAssociation('numberRangeChannels');

        $channel = $this->channelRepository->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($channel);
        $this->assertNumberRangeChannel($numberRangeId, $channel->getNumberRangeChannels());
    }

    private function createNumberRange(): string
    {
        $numberRangeId = Uuid::randomHex();

        $this->numberRangeRepository->create([[
            'id' => $numberRangeId,
            'name' => 'numberRange',
            'pattern' => '{n}',
            'start' => 0,
            'global' => false,
            'type' => [
                'id' => $numberRangeId,
                'typeName' => 'number range type',
                'technicalName' => 'number_range_type',
                'global' => false,
            ],
            'numberRangeChannels' => [
                [
                    'numberRangeId' => $numberRangeId,
                    'channelId' => TestDefaults::CHANNEL,
                    'numberRangeTypeId' => $numberRangeId,
                ],
            ],
        ]], Context::createDefaultContext());

        return $numberRangeId;
    }

    private function assertNumberRangeChannel(
        string $numberRangeId,
        ?NumberRangeChannelCollection $getNumberRangeChannels
    ): void {
        static::assertInstanceOf(NumberRangeChannelCollection::class, $getNumberRangeChannels);

        $numberRangeChannel = $getNumberRangeChannels->first();

        static::assertInstanceOf(NumberRangeChannelEntity::class, $numberRangeChannel);
        static::assertSame($numberRangeId, $numberRangeChannel->getNumberRangeId());
        static::assertSame(TestDefaults::CHANNEL, $numberRangeChannel->getChannelId());
        static::assertSame($numberRangeId, $numberRangeChannel->getNumberRangeTypeId());
    }
}
