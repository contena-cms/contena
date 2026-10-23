<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Installer\Configuration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Installer\Configuration\ShopConfigurationService;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
class ShopConfigurationServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testUpdateShop(): void
    {
        $service = new ShopConfigurationService($this->getContainer()->get('event_dispatcher'), new NativeClock());

        $connection = static::getContainer()->get(Connection::class);

        $service->updateShop([
            'name' => 'test-shop',
            'locale' => 'de-DE',
            'currency' => 'USD',
            'additionalCurrencies' => ['EUR', 'CHF'],
            'country' => 'DEU',
            'email' => 'test@test.com',
            'host' => 'localhost',
            'schema' => 'https',
            'basePath' => '/shop',
            'blueGreenDeployment' => true,
        ], $connection);

        // assert that system language was updated
        static::assertSame('Deutsch', $connection->fetchOne('SELECT `name` from `language` WHERE `id` = ?', [Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]));
        // assert that default currency was updated
        static::assertSame('USD', $connection->fetchOne('SELECT `iso_code` from `currency` WHERE `id` = ?', [Uuid::fromHexToBytes(Defaults::CURRENCY)]));

        $currencies = $connection->fetchAllKeyValue('SELECT `id`, `iso_code` from `currency`');
        // assert that not configured currencies are deleted
        static::assertEqualsCanonicalizing(['USD', 'EUR', 'CHF'], array_values($currencies));

        // assert that sales channel was created
        $id = $connection->fetchOne('SELECT `channel_id` FROM `channel_translation` WHERE `name` = ?', ['test-shop']);
        static::assertIsString($id);

        $channel = $connection->fetchAssociative('SELECT * FROM `channel` WHERE `id` = ?', [$id]);
        static::assertIsArray($channel);
        static::assertSame(Uuid::fromHexToBytes(Defaults::CURRENCY), $channel['currency_id']);
        static::assertSame(Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), $channel['language_id']);

        static::assertSame('DE', $connection->fetchOne('SELECT `iso` FROM `country` WHERE `id` = ?', [$channel['country_id']]));

        static::assertEqualsCanonicalizing(array_keys($currencies), array_values($connection->fetchFirstColumn('SELECT `currency_id` FROM `channel_currency` WHERE `channel_id` = ?', [$id])));

        $domains = $connection->fetchAllAssociative('SELECT * FROM `channel_domain` WHERE `channel_id` = ?', [$id]);
        static::assertCount(2, $domains);

        foreach ($domains as $domain) {
            static::assertSame(Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), $domain['language_id']);
            static::assertSame(Uuid::fromHexToBytes(Defaults::CURRENCY), $domain['currency_id']);
        }

        static::assertEqualsCanonicalizing([
            'https://localhost/shop',
            'http://localhost/shop',
        ], array_column($domains, 'url'));
    }
}
