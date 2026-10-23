<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Administration\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Administration\Controller\AdminUserConfigController;
use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\AdminUser\Aggregate\AdminUserConfig\AdminUserConfigCollection;
use Contena\Core\System\AdminUser\Aggregate\AdminUserConfig\AdminUserConfigDefinition;
use Contena\Core\System\AdminUser\Aggregate\AdminUserConfig\AdminUserConfigEntity;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(AdminUserConfigController::class)]
class AdminUserConfigControllerTest extends TestCase
{
    /**
     * @var StaticEntityRepository<AdminUserConfigCollection>
     */
    private StaticEntityRepository $admin_userConfigRepository;

    private AdminUserConfigController $admin_userConfigController;

    private Context $context;

    protected function setUp(): void
    {
        $this->admin_userConfigRepository = new StaticEntityRepository([], new AdminUserConfigDefinition());
        $this->admin_userConfigController = new AdminUserConfigController(
            $this->admin_userConfigRepository,
            static::createStub(Connection::class),
            new NativeClock()
        );
        $this->context = Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()));
    }

    public function testGetConfigMeReturnsEmptyData(): void
    {
        $this->admin_userConfigRepository->addSearch(new AdminUserConfigCollection());

        $response = $this->admin_userConfigController->getConfigMe($this->context, new Request());

        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"data":[]}', $response->getContent());
    }

    public function testGetConfigMeThrowsApiExceptionWhenNoUserId(): void
    {
        $this->expectExceptionObject(ApiException::admin_userNotLoggedIn());

        $this->admin_userConfigController->getConfigMe(Context::createDefaultContext(new AdminApiSource(null)), new Request());
    }

    public function testGetConfigMeThrowsInvalidContextSourceExceptionWhenWrongSource(): void
    {
        $this->expectExceptionObject(new InvalidContextSourceException(AdminApiSource::class, SystemSource::class));

        $this->admin_userConfigController->getConfigMe(Context::createDefaultContext(), new Request());
    }

    public function testGetConfigMeReturnsDataWithKeys(): void
    {
        $admin_userConfigEntity = new AdminUserConfigEntity();
        $admin_userConfigEntity->setUniqueIdentifier(Uuid::randomHex());
        $admin_userConfigEntity->setKey('testKey');
        $this->admin_userConfigRepository->addSearch(new AdminUserConfigCollection([$admin_userConfigEntity]));

        $response = $this->admin_userConfigController->getConfigMe($this->context, new Request(['keys' => ['testKey']]));

        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"data":{"testKey": null}}', $response->getContent());
    }

    public function testUpdateConfigMeReturnsEmptyDataWhenNoPostUpdateConfigs(): void
    {
        $response = $this->admin_userConfigController->updateConfigMe($this->context, new Request([], []));

        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{}', $response->getContent());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testUpdateConfigPerformsMassUpsertEmptyWhenPostUpdateConfigs(): void
    {
        $admin_userConfigEntity = new AdminUserConfigEntity();
        $admin_userConfigEntity->setId(Uuid::randomHex());
        $admin_userConfigEntity->setUniqueIdentifier(Uuid::randomHex());
        $admin_userConfigEntity->setKey('testKey');

        $this->admin_userConfigRepository->addSearch(new AdminUserConfigCollection([$admin_userConfigEntity]));

        $response = $this->admin_userConfigController->updateConfigMe($this->context, new Request([], ['blog' => true, 'testKey' => true]));

        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{}', $response->getContent());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }
}
