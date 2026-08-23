<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel\Entity;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class ChannelDefinitionTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testBusinessTimeZoneCanBeWrittenAndCleared(): void
    {
        $repository = static::getContainer()->get('channel.repository');
        $context = Context::createDefaultContext();
        $originalTimeZone = $this->getBusinessTimeZone($repository, $context);

        try {
            $repository->update([[
                'id' => TestDefaults::CHANNEL,
                'businessTimeZone' => 'Europe/Berlin',
            ]], $context);

            static::assertSame('Europe/Berlin', $this->getBusinessTimeZone($repository, $context));

            $repository->update([[
                'id' => TestDefaults::CHANNEL,
                'businessTimeZone' => null,
            ]], $context);

            static::assertNull($this->getBusinessTimeZone($repository, $context));
        } finally {
            $repository->update([[
                'id' => TestDefaults::CHANNEL,
                'businessTimeZone' => $originalTimeZone,
            ]], $context);
        }
    }

    /**
     * @param EntityRepository<ChannelCollection> $repository
     */
    private function getBusinessTimeZone(EntityRepository $repository, Context $context): ?string
    {
        $channel = $repository->search(new Criteria([TestDefaults::CHANNEL]), $context)->getEntities()->first();
        static::assertInstanceOf(ChannelEntity::class, $channel);

        return $channel->getBusinessTimeZone();
    }
}
