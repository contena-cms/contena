<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Api\FlowActionCollector;
use Contena\Core\Content\Media\Upload\MediaFileExtensionListProvider;
use Contena\Core\Framework\Api\ApiDefinition\DefinitionService;
use Contena\Core\Framework\Api\Controller\InfoController;
use Contena\Core\Framework\Api\Event\AdminInfoConfigEvent;
use Contena\Core\Framework\Api\Route\ApiRouteInfoResolver;
use Contena\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Contena\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Contena\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Contena\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderSchemaGenerator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\BusinessEventCollector;
use Contena\Core\Framework\MessageQueue\Stats\Entity\MessageStatsEntity;
use Contena\Core\Framework\MessageQueue\Stats\Entity\MessageStatsResponseEntity;
use Contena\Core\Framework\MessageQueue\Stats\Entity\MessageTypeStatsCollection;
use Contena\Core\Framework\MessageQueue\Stats\StatsService;
use Contena\Core\Framework\Migration\MigrationInfo;
use Contena\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Contena\Core\PlatformRequest;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(InfoController::class)]
class InfoControllerTest extends TestCase
{
    use EnvTestBehaviour;

    private StatsService&Stub $statsService;

    private MigrationInfo&Stub $migrationInfo;

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statsService = static::createStub(StatsService::class);
        $this->migrationInfo = static::createStub(MigrationInfo::class);
        $this->eventDispatcher = new EventDispatcher();
    }

    #[TestDox('returns the complete admin config payload with all expected keys and values')]
    public function testConfig(): void
    {
        $this->setEnvVars([
            'APP_URL' => 'https://app.url',
        ]);

        $data = $this->getConfigData();

        static::assertSame('6.8.0.0', $data['version']);
        static::assertSame('PHPUnit', $data['versionRevision']);
        static::assertSame('https://app.url', $data['appUrl']);
        static::assertSame([], $data['bundles']);

        $workerConfig = $data['adminWorker'];
        static::assertIsArray($workerConfig);
        static::assertTrue($workerConfig['enableAdminWorker']);
        static::assertTrue($workerConfig['enableNotificationWorker']);
        static::assertSame(['slow'], $workerConfig['transports']);

        $settings = $data['settings'];
        static::assertIsArray($settings);
        static::assertTrue($settings['enableUrlFeature']);
        static::assertFalse($settings['presignedUploadSupported']);
        static::assertArrayHasKey('firstMigrationDate', $settings);
        static::assertSame(['pdf', 'epub'], $settings['private_allowed_extensions']);
        static::assertContains('application/pdf', $settings['private_allowed_mime_types_by_extension']['pdf']);
        static::assertSame(['application/epub+zip'], $settings['private_allowed_mime_types_by_extension']['epub']);
        static::assertTrue($settings['enableHtmlSanitizer']);
        static::assertFalse($settings['disableExtensionManagement']);
        static::assertSame(2, $settings['minSearchTermLength']);
    }

    #[TestDox('returns content system element types as JSON')]
    public function testContentSystemElementTypes(): void
    {
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('all')->willReturn(['CT:Alert' => $this->alertTypeSpecification()]);

        $response = $this->createController(elementTypeRegistry: $registry)->getContentSystemElementTypes();

        static::assertSame(200, $response->getStatusCode());
        $content = $response->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(1, $data['types']);
        static::assertSame('CT:Alert', $data['types'][0]['name']);
        static::assertSame('core', $data['types'][0]['source']);
    }

    #[TestDox('returns the registered style options keyed by wire name with their derived schema')]
    public function testContentSystemStyleOptionsReturnsRegisteredOptionsKeyedByWireName(): void
    {
        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('allResolved')->willReturn(['col-span' => $this->styleOption()]);

        $response = $this->createController(styleOptionRegistry: $registry)->getContentSystemStyleOptions();

        $content = $response->getContent();
        static::assertIsString($content);
        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame([
            'col-span' => [
                'type' => 'integer',
                'enum' => null,
                'range' => ['min' => 1, 'max' => 12],
                'maxLength' => null,
                'default' => null,
                'breakpointAware' => true,
                'adminUI' => null,
            ],
        ], $data['styleOptions']);
    }

    #[TestDox('returns content system entity types as JSON')]
    public function testContentSystemEntityTypes(): void
    {
        $expected = ['entityTypes' => ['blog', 'category', 'landing_page']];

        $rootSourceRegistry = static::createStub(RootSourceRegistry::class);
        $rootSourceRegistry->method('entityRootSources')->willReturn(['blog', 'category', 'landing_page']);

        $controller = $this->createController(rootSourceRegistry: $rootSourceRegistry);
        $response = $controller->contentSystemEntityTypes();

        static::assertSame(200, $response->getStatusCode());
        $content = $response->getContent();
        static::assertIsString($content);
        static::assertSame($expected, json_decode($content, true, 512, \JSON_THROW_ON_ERROR));
    }

    #[TestDox('folds the registered style options into the element types response')]
    public function testContentSystemElementTypesFoldsInStyleOptions(): void
    {
        $styleOptionRegistry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $styleOptionRegistry->method('allResolved')->willReturn(['col-span' => $this->styleOption()]);

        $response = $this->createController(styleOptionRegistry: $styleOptionRegistry)->getContentSystemElementTypes();
        $content = $response->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('integer', $data['styleOptions']['col-span']['type']);
        static::assertSame(['min' => 1, 'max' => 12], $data['styleOptions']['col-span']['range']);
        static::assertTrue($data['styleOptions']['col-span']['breakpointAware']);
    }

    #[TestDox('folds source-qualified binding specifications into their matching element type')]
    public function testContentSystemElementTypesFoldsInBindingSpecifications(): void
    {
        $imageSpecification = new ContentSystemElementTypeSpecification(
            name: 'CT:Media:Image',
            label: 'Image',
            description: 'Image component',
            icon: null,
            category: null,
            copilot: new CopilotSpecification('Image summary', []),
            properties: [],
            slots: [],
            source: 'core',
        );

        $elementTypeRegistry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $elementTypeRegistry->method('all')->willReturn([
            'CT:Media:Image' => $imageSpecification,
            'CT:Alert' => $this->alertTypeSpecification(),
        ]);

        $bindingSpecificationRegistry = static::createStub(AbstractContentSystemBindingSpecificationRegistry::class);
        $bindingSpecificationRegistry->method('all')->willReturn([
            'core:media-picker' => new BindingSpecification('media-picker', 'CT:Media:Image', 'Media Picker', [], [], 'core'),
        ]);

        $response = $this->createController(
            elementTypeRegistry: $elementTypeRegistry,
            bindingSpecificationRegistry: $bindingSpecificationRegistry,
        )->getContentSystemElementTypes();
        $content = $response->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        $typesByName = [];
        foreach ($data['types'] as $type) {
            $typesByName[$type['name']] = $type;
        }

        static::assertSame([
            'core:media-picker' => [
                'id' => 'media-picker',
                'type' => 'CT:Media:Image',
                'label' => 'Media Picker',
                'default' => false,
                'resolves' => [],
                'inputs' => [],
            ],
        ], $typesByName['CT:Media:Image']['bindingSpecifications']);
        static::assertSame([], $typesByName['CT:Alert']['bindingSpecifications']);
    }

    #[TestDox('encodes the folded per-type binding specification set as a JSON object when the type has none')]
    public function testContentSystemElementTypesEncodesEmptyBindingSpecificationsAsObject(): void
    {
        $elementTypeRegistry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $elementTypeRegistry->method('all')->willReturn(['CT:Alert' => $this->alertTypeSpecification()]);

        $content = $this->createController(elementTypeRegistry: $elementTypeRegistry)
            ->getContentSystemElementTypes()
            ->getContent();

        static::assertIsString($content);
        static::assertStringContainsString('"bindingSpecifications":{}', $content);
    }

    #[TestDox('encodes the folded empty style option set as a JSON object on the element types response')]
    public function testContentSystemElementTypesEncodesEmptyStyleOptionsAsObject(): void
    {
        $content = $this->createController()->getContentSystemElementTypes()->getContent();

        static::assertIsString($content);
        static::assertStringContainsString('"styleOptions":{}', $content);
    }

    #[TestDox('encodes an empty style option set as a JSON object, not an array')]
    public function testContentSystemStyleOptionsEncodesEmptySetAsObject(): void
    {
        $content = $this->createController()->getContentSystemStyleOptions()->getContent();

        static::assertIsString($content);
        static::assertStringContainsString('"styleOptions":{}', $content);
    }

    #[TestDox('returns an empty type list when no element types are registered')]
    public function testContentSystemElementTypesReturnsEmptyWhenNoTypesRegistered(): void
    {
        $response = $this->createController()->getContentSystemElementTypes();
        $content = $response->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame([], $data['types']);
    }

    #[TestDox('returns the ContentSystem data loader schema')]
    public function testContentSystemDataLoaders(): void
    {
        $schemaGenerator = static::createStub(ContentSystemDataLoaderSchemaGenerator::class);
        $schemaGenerator->method('getSchema')->willReturn(['sources' => ['entity' => ['types' => []]]]);

        $response = $this->createController(dataLoaderSchemaGenerator: $schemaGenerator)->contentSystemDataLoaders();

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(['sources' => ['entity' => ['types' => []]]], json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    #[TestDox('allows extensions to add admin config values')]
    public function testConfigExtension(): void
    {
        $this->eventDispatcher->addListener(AdminInfoConfigEvent::class, static function (AdminInfoConfigEvent $event): void {
            $event->addConfig('foo', 'bar');
        });

        $data = $this->getConfigData();

        static::assertSame('bar', $data['foo']);
    }

    #[DataProvider('returnsFirstMigrationDateProvider')]
    #[TestDox('returns first migration date as $_dataName')]
    public function testConfigReturnsFirstMigrationDate(?string $migrationDate, ?string $expected): void
    {
        $this->migrationInfo->method('getFirstMigrationDate')->willReturn($migrationDate);

        $data = $this->getConfigData();

        static::assertSame($expected, $data['settings']['firstMigrationDate']);
    }

    #[TestDox('preserves floating-point precision in message stats response')]
    public function testMessageStatsPreservesFloatingPointPrecision(): void
    {
        $this->statsService->method('getStats')->willReturn(
            new MessageStatsResponseEntity(
                true,
                new MessageStatsEntity(1, new \DateTime('2024-01-15 10:00:00'), 1.00, new MessageTypeStatsCollection())
            )
        );

        $content = $this->createController()->messageStats()->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);
        static::assertArrayHasKey('stats', $data);
        static::assertArrayHasKey('averageTimeInQueue', $data['stats']);
        static::assertSame(1.00, $data['stats']['averageTimeInQueue']);
    }

    #[TestDox('returns disabled message stats when stats service is not enabled')]
    public function testMessageStatsReturnsDisabledWhenNotEnabled(): void
    {
        $this->statsService->method('getStats')->willReturn(new MessageStatsResponseEntity(enabled: false));

        $content = $this->createController()->messageStats()->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        static::assertFalse($data['enabled']);
        static::assertNull($data['stats']);
    }

    #[TestDox('requires message queue stats read privilege for the message stats route')]
    public function testRouteRequiresMessageQueueStatsReadPrivilege(): void
    {
        $route = new AttributeRouteControllerLoader()->load(InfoController::class)->get('api.info.message-stats');

        static::assertNotNull($route, \sprintf('Route "%s" is not defined on %s', 'api.info.message-stats', InfoController::class));
        static::assertSame(['message_queue_stats:read'], $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    /**
     * @return iterable<string, array{string|null, string|null}>
     */
    public static function returnsFirstMigrationDateProvider(): iterable
    {
        yield 'null when migration info returns null' => [null, null];
        yield 'date string from migration info' => ['2020-01-01T00:00:00.123+00:00', '2020-01-01T00:00:00.123+00:00'];
    }

    private function alertTypeSpecification(): ContentSystemElementTypeSpecification
    {
        return new ContentSystemElementTypeSpecification(
            name: 'CT:Alert',
            label: 'Alert',
            description: 'Alert component',
            icon: null,
            category: null,
            copilot: new CopilotSpecification('Alert summary', []),
            properties: [],
            slots: [],
            source: 'core',
        );
    }

    private function styleOption(): StyleOptionSpecification
    {
        return new StyleOptionSpecification(
            'col-span',
            new StyleOptionValueType(StyleOptionValueType::TYPE_INTEGER, null, ['min' => 1, 'max' => 12], null, null),
            true,
            null,
            'core',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getConfigData(): array
    {
        $content = $this->createController()
            ->config(Context::createDefaultContext(), Request::create('http://localhost'))
            ->getContent();

        static::assertIsString($content);

        return json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<string> $adminWorkerTransports
     */
    private function createController(
        array $adminWorkerTransports = ['slow'],
        ?ContentSystemDataLoaderSchemaGenerator $dataLoaderSchemaGenerator = null,
        ?AbstractContentSystemElementTypeRegistry $elementTypeRegistry = null,
        ?AbstractContentSystemStyleOptionRegistry $styleOptionRegistry = null,
        ?RootSourceRegistry $rootSourceRegistry = null,
        ?AbstractContentSystemBindingSpecificationRegistry $bindingSpecificationRegistry = null,
    ): InfoController {
        $parameterBag = new ParameterBag([
            'contena.html_sanitizer.enabled' => true,
            'contena.admin_worker.transports' => $adminWorkerTransports,
            'contena.admin_worker.enable_notification_worker' => true,
            'contena.admin_worker.enable_admin_worker' => true,
            'kernel.contena_version' => '6.8.0.0',
            'kernel.contena_version_revision' => 'PHPUnit',
            'contena.media.enable_url_upload_feature' => true,
            'contena.deployment.runtime_extension_management' => true,
        ]);

        return new InfoController(
            static::createStub(DefinitionService::class),
            $parameterBag,
            $this->migrationInfo,
            new StaticSystemConfigService(),
            static::createStub(ApiRouteInfoResolver::class),
            $this->statsService,
            $this->eventDispatcher,
            $dataLoaderSchemaGenerator ?? static::createStub(ContentSystemDataLoaderSchemaGenerator::class),
            $elementTypeRegistry ?? static::createStub(AbstractContentSystemElementTypeRegistry::class),
            $styleOptionRegistry ?? static::createStub(AbstractContentSystemStyleOptionRegistry::class),
            $rootSourceRegistry ?? static::createStub(RootSourceRegistry::class),
            $bindingSpecificationRegistry ?? static::createStub(AbstractContentSystemBindingSpecificationRegistry::class),
            null,
            new MediaFileExtensionListProvider($this->eventDispatcher, [], ['pdf', 'epub']),
            static::createStub(BusinessEventCollector::class),
            static::createStub(FlowActionCollector::class),
        );
    }
}
