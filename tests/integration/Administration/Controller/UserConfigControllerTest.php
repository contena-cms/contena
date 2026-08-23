<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Administration\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Group('slow')]
class UserConfigControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    protected function setUp(): void
    {
        $this->authorizeBrowser($this->getBrowser(), [UserVerifiedScope::IDENTIFIER], []);
    }

    protected function tearDown(): void
    {
        $this->resetBrowser();
    }

    public function testGetConfigMe(): void
    {
        $configKey = 'me.read';

        static::getContainer()->get('user_config.repository')
            ->create([[
                'userId' => $this->getUserId(),
                'key' => $configKey,
                'value' => ['content'],
            ]], Context::createDefaultContext());

        $this->getBrowser()->request('GET', '/api/_info/config-me', ['keys' => [$configKey]]);
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertSame([$configKey => ['content']], json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['data']);
    }

    public function testGetAllConfigMe(): void
    {
        $configKey = 'me.read';

        static::getContainer()->get('user_config.repository')
            ->create([[
                'userId' => $this->getUserId(),
                'key' => $configKey,
                'value' => ['content'],
            ]], Context::createDefaultContext());

        $this->getBrowser()->request('GET', '/api/_info/config-me');
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertSame([$configKey => ['content']], json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['data']);
    }

    public function testGetNullConfigMe(): void
    {
        $configKey = 'me.config';
        $ids = new IdsCollection();

        // Different user
        $user = [
            'id' => $ids->get('user'),
            'email' => 'foo@bar.com',
            'name' => 'Firstname Lastname',
            'password' => TestDefaults::HASHED_PASSWORD,
            'username' => 'foobar',
            'localeId' => static::getContainer()->get(Connection::class)->fetchOne('SELECT LOWER(HEX(id)) FROM locale LIMIT 1'),
            'aclRoles' => [],
        ];

        static::getContainer()->get('user.repository')->create([$user], Context::createDefaultContext());

        static::getContainer()->get('user_config.repository')
            ->create([[
                'userId' => $ids->get('user'),
                'key' => $configKey,
                'value' => ['content'],
            ]], Context::createDefaultContext());

        $this->getBrowser()->request('GET', '/api/_info/config-me', ['keys' => [$configKey]]);
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertSame([], json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['data']);

        // Different Key
        $userId = $this->getUserId();

        static::getContainer()->get('user_config.repository')
            ->create([[
                'userId' => $userId,
                'key' => $configKey,
                'value' => ['content'],
            ]], Context::createDefaultContext());
        $this->getBrowser()->request('GET', '/api/_info/config-me', ['keys' => ['random-key']]);
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertSame([], json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['data']);
    }

    public function testUpdateConfigMe(): void
    {
        $configKey = 'me.config';
        $anotherConfigKey = 'random.key';
        $anotherValue = 'random.value';

        static::getContainer()->get('user_config.repository')
            ->create([[
                'userId' => $this->getUserId(),
                'key' => $configKey,
                'value' => ['content'],
            ]], Context::createDefaultContext());

        $newValue = 'another-content';
        $this->getBrowser()->jsonRequest(
            'POST',
            '/api/_info/config-me',
            [
                $configKey => [$newValue],
                $anotherConfigKey => [$anotherValue],
            ],
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $this->getBrowser()->request('GET', '/api/_info/config-me', ['keys' => [$configKey, $anotherConfigKey]]);
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertSame([
            $configKey => [$newValue],
            $anotherConfigKey => [$anotherValue],
        ], json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['data']);
    }

    public function testCreateConfigMe(): void
    {
        $configKey = 'me.config';
        $newValue = 'another-content';
        $this->getBrowser()->jsonRequest(
            'POST',
            '/api/_info/config-me',
            [
                $configKey => [$newValue],
            ]
        );

        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $this->getBrowser()->request('GET', '/api/_info/config-me', ['keys' => [$configKey]]);
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertSame([$configKey => [$newValue]], json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['data']);
    }

    public function testPlatformUserConfigRemainsPlatformOwnedWhenManagingATenant(): void
    {
        $tenantId = Uuid::randomHex();
        static::getContainer()->get('tenant.repository')->create([[
            'id' => $tenantId,
            'name' => 'User config tenant',
            'code' => 'user-config-' . \substr($tenantId, 0, 8),
            'status' => true,
        ]], Context::createDefaultContext());

        $configKey = 'platform-user.' . Uuid::randomHex();
        $client = $this->getBrowser();
        $client->setServerParameter('HTTP_SW_TENANT_ID', $tenantId);
        $client->jsonRequest('POST', '/api/_info/config-me', [$configKey => ['platform-value']]);

        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $tenantId = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(`tenant_id`)) FROM `user_config` WHERE `user_id` = :userId AND `key` = :key',
            [
                'userId' => Uuid::fromHexToBytes($this->getUserId()),
                'key' => $configKey,
            ],
        );
        static::assertNull($tenantId);

        $client->request('GET', '/api/_info/config-me', ['keys' => [$configKey]]);
        $content = $client->getResponse()->getContent();
        static::assertIsString($content);
        static::assertSame([$configKey => ['platform-value']], json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['data']);
    }

    public function testCreateWithSendingEmptyParameter(): void
    {
        $this->getBrowser()->request('POST', '/api/_info/config-me');
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $this->getBrowser()->jsonRequest('POST', '/api/_info/config-me');
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    private function getUserId(): string
    {
        $context = $this->getBrowser()->getServerParameter(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        static::assertInstanceOf(Context::class, $context);
        $source = $context->getSource();
        static::assertInstanceOf(AdminApiSource::class, $source);
        $userId = $source->getUserId();
        static::assertIsString($userId);

        return Uuid::fromBytesToHex($userId);
    }
}
