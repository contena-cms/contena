<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Acl;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Acl\AclAnnotationValidator;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Exception\MissingPrivilegeException;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Test\Api\Acl\fixtures\AclTestController;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Kernel;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class AclAnnotationValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    private Connection $connection;

    private AclAnnotationValidator $validator;

    protected function setUp(): void
    {
        $this->appRepository = static::getContainer()->get('app.repository');
        $this->connection = static::getContainer()->get(Connection::class);
        $this->validator = new AclAnnotationValidator($this->connection);
    }

    /**
     * @param list<string> $privileges
     * @param list<string> $acl
     */
    #[DataProvider('annotationProvider')]
    public function testValidateRequestAsRouteAttribute(array $privileges, array $acl, bool $pass): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions($privileges);

        $context = new Context(
            $source,
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM]
        );

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ACL, $acl);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);

        $kernel = static::createStub(Kernel::class);

        $exception = null;

        $controller = new AclTestController();

        try {
            $this->validator->validate(new ControllerEvent($kernel, $controller->testRoute(...), $request, 1));
        } catch (\Exception $e) {
            $exception = $e;
        }

        if ($pass) {
            static::assertNull($exception, 'Exception: ' . ($exception !== null ? \print_r($exception->getMessage(), true) : 'No Exception'));
        } else {
            static::assertInstanceOf(MissingPrivilegeException::class, $exception, 'Exception: ' . ($exception !== null ? \print_r($exception->getMessage(), true) : 'No Exception'));
        }
    }

    public function testValidateAppRequest(): void
    {
        $actionId = Uuid::randomHex();
        $appName = 'AppSuccess';
        $this->registerActionButton($appName, $actionId);

        $source = new AdminApiSource(null, null);
        $source->setPermissions(['app.' . $appName]);
        $context = new Context(
            $source,
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM]
        );

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ACL, ['app']);
        $request->attributes->set('id', $actionId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);

        $kernel = static::createStub(Kernel::class);

        $exception = null;

        $controller = new AclTestController();

        try {
            $this->validator->validate(new ControllerEvent($kernel, $controller->testRoute(...), $request, 1));
        } catch (\Exception $e) {
            $exception = $e;
        }

        static::assertNull($exception, 'Exception: ' . ($exception !== null ? \print_r($exception->getMessage(), true) : 'No Exception'));
    }

    public function testValidateAppRequestFail(): void
    {
        $actionId = Uuid::randomHex();
        $appName = 'AppFail';
        $this->registerActionButton($appName, $actionId);

        $source = new AdminApiSource(null, null);
        $source->setPermissions(['app.fail']);
        $context = new Context(
            $source,
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM]
        );

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ACL, ['app']);
        $request->attributes->set('id', $actionId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);

        $kernel = static::createStub(Kernel::class);

        $exception = null;

        $controller = new AclTestController();

        try {
            $this->validator->validate(new ControllerEvent($kernel, $controller->testRoute(...), $request, 1));
        } catch (\Exception $e) {
            $exception = $e;
        }

        static::assertInstanceOf(MissingPrivilegeException::class, $exception, 'Exception: ' . ($exception !== null ? \print_r($exception->getMessage(), true) : 'No Exception'));
    }

    /**
     * @return iterable<string, array{0: list<string>, 1: list<string>, 2: bool}>
     */
    public static function annotationProvider(): iterable
    {
        yield 'blog write privilege is accepted when no annotation is required' => [
            ['blog:write'], [], true,
        ];
        yield 'missing blog write privilege is rejected' => [
            [], ['blogWrite'], false,
        ];
        yield 'matching blog write privilege is accepted' => [
            ['blog:write'], ['blog:write'], true,
        ];
        yield 'matching blog write and read privileges are accepted' => [
            ['blog:write', 'blog:read'], ['blog:write', 'blog:read'], true,
        ];
        yield 'missing blog read privilege is rejected' => [
            ['blog:write'], ['blog:write', 'blog:read'], false,
        ];
        yield 'matching route privilege is accepted' => [
            ['api.test.route'], ['api.test.route'], true,
        ];
        yield 'missing route privilege is rejected' => [
            [], ['api.test.route'], false,
        ];
        yield 'entity privileges do not satisfy route privilege' => [
            ['blog:write', 'blog:read'], ['api.test.route'], false,
        ];
        yield 'app wildcard privilege satisfies app annotation' => [
            ['app.all'], ['app'], true,
        ];
    }

    private function registerActionButton(string $appName, string $actionId): void
    {
        $iconRaw = \file_get_contents(__DIR__ . '/../../App/Manifest/_fixtures/test/icon.png');
        static::assertIsString($iconRaw);

        $this->appRepository->create([[
            'name' => $appName,
            'active' => true,
            'path' => __DIR__ . '/../../App/Manifest/_fixtures/test',
            'iconRaw' => $iconRaw,
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'actionButtons' => [
                [
                    'id' => $actionId,
                    'entity' => 'order',
                    'view' => 'detail',
                    'action' => 'orderDetailApp1',
                    'label' => 'Order Detail App1',
                    'url' => 'app1.com/order/detail',
                ],
            ],
            'integration' => [
                'label' => $appName,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => $appName,
            ],
        ]], Context::createDefaultContext());
    }
}
