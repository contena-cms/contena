<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Controller\RegionController;
use Contena\Frontend\Pagelet\Region\RegionDataPagelet;
use Contena\Frontend\Pagelet\Region\RegionDataPageletCriteriaEvent;
use Contena\Frontend\Pagelet\Region\RegionDataPageletLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class RegionControllerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private string $countryId;

    private RegionController $regionController;

    private ChannelContext $channelContext;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->countryId = $this->getCountryIdByIso('CN');
        $this->regionController = static::getContainer()->get(RegionController::class);
        $this->channelContext = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);
    }

    public function testGetRootRegionData(): void
    {
        $response = $this->regionController->getRegionData(
            new Request(['countryId' => $this->countryId]),
            $this->channelContext,
        );

        static::assertCount(34, $this->regions($response->getContent()));
    }

    public function testGetThreeRegionLevels(): void
    {
        $provinceId = $this->getRegionIdByCode('11');
        $cityId = $this->getRegionIdByCode('1101');

        $cityResponse = $this->regionController->getRegionData(
            new Request(['countryId' => $this->countryId, 'parentId' => $provinceId]),
            $this->channelContext,
        );
        $districtResponse = $this->regionController->getRegionData(
            new Request(['countryId' => $this->countryId, 'parentId' => $cityId]),
            $this->channelContext,
        );

        $cities = $this->regions($cityResponse->getContent());
        $districts = $this->regions($districtResponse->getContent());

        static::assertCount(1, $cities);
        static::assertSame('1101', $cities[0]['code']);
        static::assertCount(16, $districts);
        static::assertContains('110101', array_column($districts, 'code'));
    }

    public function testEmptyCountryId(): void
    {
        $this->expectExceptionObject(RoutingException::missingRequestParameter('countryId'));
        $this->regionController->getRegionData(new Request(), $this->channelContext);
    }

    public function testRegionControllerEvents(): void
    {
        $dispatcher = static::getContainer()->get('event_dispatcher');

        $testSubscriber = new RegionControllerTestSubscriber();
        $dispatcher->addSubscriber($testSubscriber);

        $this->regionController->getRegionData(
            new Request(['countryId' => $this->countryId]),
            $this->channelContext,
        );

        $dispatcher->removeSubscriber($testSubscriber);

        static::assertInstanceOf(RegionDataPagelet::class, $testSubscriber->testPagelet);
        static::assertInstanceOf(RegionDataPageletCriteriaEvent::class, $testSubscriber->criteriaEvent);
    }

    private function getCountryIdByIso(string $iso): string
    {
        $countryId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM country WHERE iso = :iso', ['iso' => $iso]);
        static::assertIsString($countryId);

        return $countryId;
    }

    private function getRegionIdByCode(string $code): string
    {
        $regionId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(id)) FROM region WHERE country_id = UNHEX(:countryId) AND code = :code',
            ['countryId' => $this->countryId, 'code' => $code],
        );
        static::assertIsString($regionId);

        return $regionId;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function regions(string|false $content): array
    {
        /** @var array{regions: list<array<string, mixed>>} $decoded */
        $decoded = json_decode((string) $content, true, 512, \JSON_THROW_ON_ERROR);

        return $decoded['regions'];
    }
}

/**
 * @internal
 */
class RegionControllerTestSubscriber implements EventSubscriberInterface
{
    public ?RegionDataPagelet $testPagelet = null;

    public ?RegionDataPageletCriteriaEvent $criteriaEvent = null;

    public static function getSubscribedEvents(): array
    {
        return [
            RegionDataPageletLoadedEvent::class => 'onPageletLoaded',
            RegionDataPageletCriteriaEvent::class => 'onCriteria',
        ];
    }

    public function onPageletLoaded(RegionDataPageletLoadedEvent $event): void
    {
        $this->testPagelet = $event->getPagelet();
    }

    public function onCriteria(RegionDataPageletCriteriaEvent $event): void
    {
        $this->criteriaEvent = $event;
    }
}
