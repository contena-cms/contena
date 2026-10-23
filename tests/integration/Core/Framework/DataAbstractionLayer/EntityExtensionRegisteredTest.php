<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Integration\IntegrationDefinition;

/**
 * @internal
 */
class EntityExtensionRegisteredTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testAdmin(): void
    {
        try {
            $integrationRepository = static::getContainer()->get('integration.repository');
        } catch (\Exception $e) {
            static::markTestSkipped('Integration repository is not available');
        }

        static::assertInstanceOf(EntityRepository::class, $integrationRepository);
        $definition = $integrationRepository->getDefinition();
        static::assertInstanceOf(IntegrationDefinition::class, $definition);

        $fields = $definition->getFields();
        static::assertTrue($fields->has('createdNotifications'));
    }

    public function testFrontend(): void
    {
        if (!static::getContainer()->has('theme.repository')) {
            static::markTestSkipped('Theme repository is not available');
        }

        $channel = static::getContainer()->get('channel.repository');

        static::assertInstanceOf(EntityRepository::class, $channel);
        $definition = $channel->getDefinition();
        static::assertInstanceOf(ChannelDefinition::class, $definition);

        $fields = $definition->getFields();
        static::assertTrue($fields->has('themes'));
    }
}
