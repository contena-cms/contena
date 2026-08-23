<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Event;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Country\CountryDefinition;

/**
 * @internal
 */
class EntityWrittenEventSerializationTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testEventCanBeSerialized(): void
    {
        $container = $this->writeTestCountry();
        $event = $container->getEventByEntityName(CountryDefinition::ENTITY_NAME);

        $encoded = json_encode($event, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($encoded);
        static::assertJson($encoded);

        $encoded = json_encode($container, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($encoded);
        static::assertJson($encoded);
    }

    private function writeTestCountry(): EntityWrittenContainerEvent
    {
        /** @var EntityRepository<CountryCollection> $countryRepository */
        $countryRepository = static::getContainer()->get('country.repository');

        return $countryRepository->create(
            [[
                'id' => Uuid::randomHex(),
                'name' => 'Serialization Test Country',
                'iso' => 'SX',
                'iso3' => 'SXT',
            ]],
            Context::createDefaultContext()
        );
    }
}
