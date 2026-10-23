<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\TestCaseBase;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait CountryAddToChannelTestBehaviour
{
    abstract protected static function getContainer(): ContainerInterface;

    abstract protected function getValidCountryId(?string $channelId = TestDefaults::CHANNEL): string;

    /**
     * @param array<string> $additionalCountryIds
     */
    protected function addCountriesToChannel(array $additionalCountryIds = [], string $channelId = TestDefaults::CHANNEL): void
    {
        /** @var EntityRepository<ChannelCollection> $channelRepository */
        $channelRepository = static::getContainer()->get('channel.repository');

        $countryIds = array_merge([
            ['id' => $this->getValidCountryId($channelId)],
        ], array_map(static fn (string $countryId) => ['id' => $countryId], $additionalCountryIds));

        $channelRepository->update([[
            'id' => $channelId,
            'countries' => $countryIds,
        ]], Context::createDefaultContext());
    }
}
