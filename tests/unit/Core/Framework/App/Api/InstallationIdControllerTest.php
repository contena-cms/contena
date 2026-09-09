<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Api;

use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\App\Api\InstallationIdController;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Exception\InstallationIdChangeStrategyNotFoundException;
use Contena\Core\Framework\App\InstallationId\FingerprintComparisonResult;
use Contena\Core\Framework\App\InstallationId\FingerprintMatch;
use Contena\Core\Framework\App\InstallationId\FingerprintMismatch;
use Contena\Core\Framework\App\InstallationId\InstallationId;
use Contena\Core\Framework\App\InstallationId\InstallationIdProvider;
use Contena\Core\Framework\App\InstallationIdChangeResolver\Resolver;
use Contena\Core\Framework\Context;
use Contena\Core\PlatformRequest;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(InstallationIdController::class)]
class InstallationIdControllerTest extends TestCase
{
    private InstallationIdController $controller;

    private Resolver&MockObject $installationIdChangeResolver;

    private InstallationIdProvider&Stub $installationIdProvider;

    /**
     * @var StaticEntityRepository<AppCollection>
     */
    private StaticEntityRepository $appRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->installationIdChangeResolver = $this->createMock(Resolver::class);
        $this->installationIdProvider = static::createStub(InstallationIdProvider::class);
        $this->appRepository = new StaticEntityRepository([]);
        $this->controller = new InstallationIdController($this->installationIdChangeResolver, $this->installationIdProvider, $this->appRepository);
        $this->context = Context::createDefaultContext(new AdminApiSource(null));
    }

    public function testGetAvailableStrategies(): void
    {
        $this->installationIdChangeResolver->expects($this->once())
            ->method('getAvailableStrategies')
            ->willReturn($expectedStrategies = [
                ['strategy1', 'description1'],
                ['strategy2', 'description2'],
            ]);

        $response = $this->controller->getAvailableStrategies();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $body = $response->getContent();
        static::assertIsString($body);

        $strategies = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame($strategies, $expectedStrategies);
    }

    public function testChangesInstallationIdUsingTheProvidedStrategy(): void
    {
        $request = new Request(request: ['strategy' => 'testStrategy']);

        $this->installationIdChangeResolver->expects($this->once())
            ->method('resolve')
            ->with($request->request->get('strategy'), $this->context);

        $response = $this->controller->changeInstallationId($request, $this->context);
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertEmpty($response->getContent());
    }

    public function testFailsIfResolverThrowsWhenChangingInstallationId(): void
    {
        $request = new Request(request: ['strategy' => 'testStrategy']);

        $this->installationIdChangeResolver->expects($this->once())
            ->method('resolve')
            ->with($request->request->get('strategy'), $this->context)
            ->willThrowException(AppException::installationIdChangeResolveStrategyNotFound('testStrategy'));

        static::expectExceptionObject(new InstallationIdChangeStrategyNotFoundException('testStrategy'));
        $this->controller->changeInstallationId($request, $this->context);
    }

    public function testFailsIfNoStrategyIsProvided(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource(null));
        $request = new Request();

        $this->installationIdChangeResolver->expects($this->never())
            ->method('resolve');

        static::expectExceptionObject(AppException::missingRequestParameter('strategy'));
        $this->controller->changeInstallationId($request, $context);
    }

    public function testGetFingerprintsWhenInstallationIdChangeSuggested(): void
    {
        $installationId = InstallationId::create('123456789');
        $fingerprints = new FingerprintComparisonResult([
            'fingerprint1' => new FingerprintMatch('fingerprint1', 'value1', 25),
        ], [
            'fingerprint2' => new FingerprintMismatch('fingerprint2', 'value2', 'expectedValue2', 50),
            'fingerprint3' => new FingerprintMismatch('fingerprint3', 'value3', 'expectedValue3', 75),
        ], 75);

        $this->installationIdChangeResolver->expects($this->never())
            ->method('resolve');

        $installationIdProvider = static::createMock(InstallationIdProvider::class);
        $installationIdProvider->expects($this->once())
            ->method('getInstallationId')
            ->willThrowException(AppException::installationIdChangeSuggested($installationId, $fingerprints));
        $controller = new InstallationIdController($this->installationIdChangeResolver, $installationIdProvider, $this->appRepository);

        $this->appRepository->addSearch(new AppCollection([
            new AppEntity()->assign(['id' => 'app-1', 'translated' => ['label' => 'App 1']]),
            new AppEntity()->assign(['id' => 'app-2', 'translated' => ['label' => 'App 2']]),
        ]));

        $response = $controller->checkInstallationId($this->context);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $body = $response->getContent();
        static::assertIsString($body);

        $comparisonResult = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
        static::assertEquals([
            'fingerprints' => [
                'matchingFingerprints' => [
                    'fingerprint1' => ['identifier' => 'fingerprint1', 'storedStamp' => 'value1', 'score' => 25],
                ],
                'mismatchingFingerprints' => [
                    'fingerprint2' => ['identifier' => 'fingerprint2', 'storedStamp' => 'value2', 'expectedStamp' => 'expectedValue2', 'score' => 50],
                    'fingerprint3' => ['identifier' => 'fingerprint3', 'storedStamp' => 'value3', 'expectedStamp' => 'expectedValue3', 'score' => 75],
                ],
                'score' => 125,
                'threshold' => 75,
            ],
            'apps' => ['App 1', 'App 2'],
        ], $comparisonResult);
    }

    public function testGetFingerprintsWhenInstallationIdChangeNotSuggested(): void
    {
        $installationId = InstallationId::create('123456789');

        $this->installationIdChangeResolver->expects($this->never())
            ->method('resolve');

        $installationIdProvider = static::createMock(InstallationIdProvider::class);
        $installationIdProvider->expects($this->once())
            ->method('getInstallationId')
            ->willReturn($installationId);
        $controller = new InstallationIdController($this->installationIdChangeResolver, $installationIdProvider, $this->appRepository);

        $response = $controller->checkInstallationId($this->context);

        static::assertEmpty($response->getContent());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testChangeInstallationIdRouteRequiresAppChangeAclPrivilege(): void
    {
        $this->installationIdChangeResolver->expects($this->never())->method('resolve');

        $route = new AttributeRouteControllerLoader()->load(InstallationIdController::class)->get('api.app_system.installation_id.change');

        static::assertNotNull($route);
        static::assertSame(['system:app:change'], $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }
}
