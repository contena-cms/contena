<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Script\Debugging\ScriptTraces;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Controller\CountryStateController;
use Contena\Frontend\Pagelet\Country\CountryStateDataPagelet;
use Contena\Frontend\Pagelet\Country\CountryStateDataPageletCriteriaEvent;
use Contena\Frontend\Pagelet\Country\CountryStateDataPageletLoadedEvent;
use Contena\Frontend\Pagelet\Country\CountryStateDataPageletLoadedHook;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;

/**
 * @internal
 */
class CountryStateControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendControllerTestBehaviour;

    private Connection $connection;

    private string $countryIdDE;

    private string $countryIdAT;

    private CountryStateController $countryStateController;

    private ChannelContext $channelContext;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);

        $this->countryIdDE = $this->getCountryIdByIso();
        $this->countryIdAT = $this->getCountryIdByIso('AT');

        $this->countryStateController = static::getContainer()->get(CountryStateController::class);

        $this->channelContext = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);
    }

    public function testGetCountryData(): void
    {
        $response = $this->countryStateController->getCountryData(new Request([], ['countryId' => $this->countryIdDE]), $this->channelContext);

        static::assertCount(16, \json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['states']);
    }

    public function testGetCountryDataFromQueryParameter(): void
    {
        $response = $this->countryStateController->getCountryData(new Request(['countryId' => $this->countryIdAT]), $this->channelContext);

        static::assertCount(0, \json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['states']);
    }

    public function testGetCountryDataPrefersQueryParameterOverPostData(): void
    {
        $response = $this->countryStateController->getCountryData(
            new Request(['countryId' => $this->countryIdDE], ['countryId' => $this->countryIdAT]),
            $this->channelContext
        );

        $states = \json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['states'];

        static::assertCount(16, $states);
    }

    public function testEmptyCountryId(): void
    {
        $this->expectExceptionObject(RoutingException::missingRequestParameter('countryId'));
        $this->countryStateController->getCountryData(new Request(), $this->channelContext);
    }

    public function testCountryStateControllerEvents(): void
    {
        $testSubscriber = new CountryStateControllerTestSubscriber();
        EventHookDispatcher::fromContainer(static::getContainer())->subscribe($testSubscriber);

        $this->countryStateController->getCountryData(new Request([], ['countryId' => $this->countryIdDE]), $this->channelContext);

        static::assertInstanceOf(CountryStateDataPagelet::class, $testSubscriber->testPagelet);
        static::assertInstanceOf(CountryStateDataPageletCriteriaEvent::class, $testSubscriber->criteriaEvent);
    }

    public function testCountryStateControllerHooks(): void
    {
        $appId = Uuid::randomHex();
        $roleId = Uuid::randomHex();
        $integrationId = Uuid::randomHex();

        static::getContainer()->get('app.repository')->create([[
            'id' => $appId,
            'name' => 'Test',
            'path' => __DIR__ . '/fixtures/Apps/frontend-endpoint-cases',
            'active' => true,
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'actionButtons' => [
                [
                    'entity' => 'order',
                    'view' => 'detail',
                    'action' => 'test',
                    'label' => 'test',
                    'url' => 'test.com',
                ],
            ],
            'integration' => [
                'id' => $integrationId,
                'label' => 'test',
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'id' => $roleId,
                'name' => 'Test',
            ],
            'scripts' => [
                [
                    'name' => 'country-loaded/loaded.script.twig',
                    'hook' => 'country-state-data-pagelet-loaded',
                    'script' => '{% do debug.dump(hook.getPage.getStates.count) %}',
                    'active' => true,
                ],
            ],
        ]], Context::createDefaultContext());

        $request = new Request([], ['countryId' => $this->countryIdDE]);
        static::getContainer()->get('request_stack')->push($request);

        $this->countryStateController->getCountryData($request, $this->channelContext);

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(CountryStateDataPageletLoadedHook::HOOK_NAME, $traces);

        static::assertSame([16], $traces['country-state-data-pagelet-loaded'][0]['output']);
    }

    private function getCountryIdByIso(string $iso = 'DE'): string
    {
        $countryId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM country WHERE iso = :iso', ['iso' => $iso]);
        static::assertIsString($countryId);

        return $countryId;
    }
}

/**
 * @internal
 */
class CountryStateControllerTestSubscriber implements EventSubscriberInterface
{
    public ?CountryStateDataPagelet $testPagelet = null;

    public ?CountryStateDataPageletCriteriaEvent $criteriaEvent = null;

    public static function getSubscribedEvents(): array
    {
        return [
            CountryStateDataPageletLoadedEvent::class => 'onPageletLoaded',
            CountryStateDataPageletCriteriaEvent::class => 'onCriteria',
        ];
    }

    public function onPageletLoaded(CountryStateDataPageletLoadedEvent $event): void
    {
        $this->testPagelet = $event->getPagelet();
    }

    public function onCriteria(CountryStateDataPageletCriteriaEvent $event): void
    {
        $this->criteriaEvent = $event;
    }
}
