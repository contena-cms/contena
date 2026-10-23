<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\Test\Integration\Traits\UserTestTrait;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('store-api')]
class ChangePasswordRouteTest extends TestCase
{
    use UserTestTrait;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    private string $email;

    private string $contextToken;

    private string $userId;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignChannelContext($this->browser);

        $this->email = Uuid::randomHex() . '@example.com';
        $this->userId = $this->createUser($this->email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $this->email,
                    'password' => 'contena',
                ]
            );

        $response = $this->browser->getResponse();

        $this->contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $this->contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->contextToken);
    }

    public function testEmptyRequest(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-password',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('VIOLATION::IS_BLANK_ERROR', $response['errors'][0]['code']);
    }

    public function testChangeInvalidPassword(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-password',
                [
                    'password' => 'foooware',
                    'newPassword' => 'foooware',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('VIOLATION::CUSTOMER_PASSWORD_NOT_CORRECT', $response['errors'][0]['code']);
    }

    public function testChangeAndLogin(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-password',
                [
                    'password' => 'contena',
                    'newPassword' => 'foooware',
                    'newPasswordConfirm' => 'foooware',
                ]
            );

        $response = $this->browser->getResponse();

        $responseContent = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayNotHasKey('errors', $responseContent);

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $this->email,
                    'password' => 'foooware',
                ]
            );

        $response = $this->browser->getResponse();

        $responseContent = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayNotHasKey('errors', $responseContent);

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);
    }

    public function testContextTokenIsReplacedAfterChangingPassword(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-password',
                [
                    'password' => 'contena',
                    'newPassword' => 'foooware',
                    'newPasswordConfirm' => 'foooware',
                ]
            );

        $response = $this->browser->getResponse();

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        $oldContextExists = static::getContainer()->get(ChannelContextPersister::class)->load($this->contextToken, $this->ids->get('sales-channel'));

        static::assertCount(0, $oldContextExists);

        // Token is replaced
        static::assertNotSame($this->contextToken, $contextToken);

        $newContextExists = static::getContainer()->get(ChannelContextPersister::class)->load($contextToken, $this->ids->get('sales-channel'), $this->userId);

        static::assertNotCount(0, $newContextExists);
    }
}
