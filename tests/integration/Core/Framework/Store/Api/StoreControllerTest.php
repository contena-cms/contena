<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Store\Api;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Store\Api\StoreController;
use Contena\Core\Framework\Store\Exception\StoreApiException;
use Contena\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Contena\Core\Framework\Store\Services\StoreClient;
use Contena\Core\Framework\Store\StoreException;
use Contena\Core\Framework\Store\Struct\PluginDownloadDataStruct;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\AdminUser\AdminUserEntity;
use Symfony\Component\HttpFoundation\Request;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class StoreControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use KernelTestBehaviour;

    private Context $defaultContext;

    /**
     * @var EntityRepository<AdminUserCollection>
     */
    private EntityRepository $adminUserRepository;

    protected function setUp(): void
    {
        $this->defaultContext = Context::createDefaultContext();
        $this->admin_userRepository = static::getContainer()->get('admin_user.repository');
    }

    public function testCheckLoginWithoutStoreToken(): void
    {
        /** @var AdminUserEntity $adminUser */
        $adminUser = $this->admin_userRepository->search(new Criteria(), $this->defaultContext)->getEntities()->first();

        $storeController = $this->getStoreController();
        $context = new Context(new AdminApiSource($adminUser->getId()));

        $response = $storeController->checkLogin($context)->getContent();
        static::assertIsString($response);

        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);
        static::assertNull($response['admin_userInfo']);
    }

    public function testLoginWithCorrectCredentials(): void
    {
        $request = new Request([], [
            'contenaId' => 'j.doe@contena.cn',
            'password' => 'v3rys3cr3t',
        ]);

        /** @var AdminUserEntity $adminUser */
        $adminUser = $this->admin_userRepository->search(new Criteria(), $this->defaultContext)->getEntities()->first();

        $context = new Context(new AdminApiSource($adminUser->getId()));

        $storeClientMock = $this->createMock(StoreClient::class);
        $storeClientMock->expects($this->once())
            ->method('loginWithContenaId')
            ->with('j.doe@contena.cn', 'v3rys3cr3t');

        $storeController = $this->getStoreController($storeClientMock);

        $response = $storeController->login($request, $context);

        static::assertSame(200, $response->getStatusCode());
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $request = new Request([], [
            'contenaId' => 'j.doe@contena.cn',
            'password' => 'v3rys3cr3t',
        ]);

        /** @var AdminUserEntity $adminUser */
        $adminUser = $this->admin_userRepository->search(new Criteria(), $this->defaultContext)->getEntities()->first();

        $context = new Context(new AdminApiSource($adminUser->getId()));

        $clientExceptionMock = static::createStub(ClientException::class);
        $clientExceptionMock->method('getResponse')
            ->willReturn(new Response());

        $storeClientMock = $this->createMock(StoreClient::class);
        $storeClientMock->expects($this->once())
            ->method('loginWithContenaId')
            ->willThrowException($clientExceptionMock);

        $storeController = $this->getStoreController($storeClientMock);

        static::expectException(StoreApiException::class);
        $storeController->login($request, $context);
    }

    public function testLoginWithInvalidCredentialsInput(): void
    {
        $request = new Request([], [
            'contenaId' => null,
            'password' => null,
        ]);

        /** @var AdminUserEntity $adminUser */
        $adminUser = $this->admin_userRepository->search(new Criteria(), $this->defaultContext)->getEntities()->first();

        $context = new Context(new AdminApiSource($adminUser->getId()));

        $storeClientMock = $this->createMock(StoreClient::class);
        $storeClientMock->expects($this->never())
            ->method('loginWithContenaId');

        $storeController = $this->getStoreController($storeClientMock);

        static::expectExceptionObject(StoreException::invalidCredentials());
        $storeController->login($request, $context);
    }

    public function testCheckLoginWithStoreToken(): void
    {
        /** @var AdminUserEntity $adminUser */
        $adminUser = $this->admin_userRepository->search(new Criteria(), $this->defaultContext)->getEntities()->first();

        $this->admin_userRepository->update([[
            'id' => $adminUser->getId(),
            'storeToken' => 'store-token',
        ]], $this->defaultContext);

        $storeController = $this->getStoreController();
        $context = new Context(new AdminApiSource($adminUser->getId()));

        $response = $storeController->checkLogin($context)->getContent();
        static::assertIsString($response);

        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($response['admin_userInfo'], [
            'name' => 'John Doe',
            'email' => 'john.doe@contena.cn',
        ]);
    }

    public function testCheckLoginWithMultipleStoreTokens(): void
    {
        /** @var AdminUserEntity $adminUser */
        $adminUser = $this->admin_userRepository->search(new Criteria(), $this->defaultContext)->getEntities()->first();

        $this->admin_userRepository->update([[
            'id' => $adminUser->getId(),
            'storeToken' => 'store-token',
            'name' => 'John',
        ]], $this->defaultContext);

        $this->admin_userRepository->create([[
            'id' => Uuid::randomHex(),
            'name' => 'Jane',
            'lastName' => 'Doe',
            'storeToken' => 'store-token-two',
            'localeId' => $adminUser->getLocaleId(),
            'username' => 'admin-two',
            'password' => 's3cr3t12345',
            'email' => 'jane.doe@contena.cn',
        ]], $this->defaultContext);

        $storeController = $this->getStoreController();
        $context = new Context(new AdminApiSource($adminUser->getId()));

        $response = $storeController->checkLogin($context)->getContent();
        static::assertIsString($response);

        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($response['admin_userInfo'], [
            'name' => 'John Doe',
            'email' => 'john.doe@contena.cn',
        ]);
    }

    private function getStoreController(
        ?StoreClient $storeClient = null,
    ): StoreController {
        return new StoreController(
            $storeClient ?? $this->getStoreClientMock(),
            $this->admin_userRepository,
            static::getContainer()->get(AbstractExtensionDataProvider::class)
        );
    }

    /**
     * @return StoreClient&Stub
     */
    private function getStoreClientMock(): StoreClient
    {
        $storeClient = static::createStub(StoreClient::class);

        $storeClient->method('getDownloadDataForPlugin')
            ->willReturn($this->getPluginDownloadDataStub());

        $storeClient->method('admin_userInfo')
            ->willReturn([
                'name' => 'John Doe',
                'email' => 'john.doe@contena.cn',
            ]);

        return $storeClient;
    }

    private function getPluginDownloadDataStub(): PluginDownloadDataStruct
    {
        return (new PluginDownloadDataStruct())
            ->assign([
                'location' => 'not-null',
            ]);
    }
}
