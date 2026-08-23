<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('channel-api')]
class ChangePasswordRouteTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    private string $email;

    private string $contextToken;

    private string $memberId;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('channel'),
        ]);

        $this->email = Uuid::randomHex() . '@example.com';
        $this->memberId = Uuid::randomHex();

        static::getContainer()->get('member.repository')->create([[
            'id' => $this->memberId,
            'channelId' => $this->ids->get('channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'groupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'email' => $this->email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max Member',
            'memberNumber' => Uuid::randomHex(),
            'active' => true,
        ]], Context::createDefaultContext());

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/login',
                [
                    'email' => $this->email,
                    'password' => 'contenaAdmin',
                ]
            );

        $response = $this->browser->getResponse();

        $this->contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($this->contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->contextToken);
    }

    public function testEmptyRequest(): void
    {
        $this->browser
            ->request(
                'POST',
                '/channel-api/account/change-password',
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
                '/channel-api/account/change-password',
                [
                    'password' => 'invalid-password',
                    'newPassword' => 'new-password',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('VIOLATION::MEMBER_PASSWORD_NOT_CORRECT', $response['errors'][0]['code']);
    }

    public function testChangeAndLogin(): void
    {
        $this->browser
            ->request(
                'POST',
                '/channel-api/account/change-password',
                [
                    'password' => 'contenaAdmin',
                    'newPassword' => 'new-password',
                    'newPasswordConfirm' => 'new-password',
                ]
            );

        $response = $this->browser->getResponse();

        $responseContent = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayNotHasKey('errors', $responseContent);

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/login',
                [
                    'email' => $this->email,
                    'password' => 'new-password',
                ]
            );

        $response = $this->browser->getResponse();

        $responseContent = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayNotHasKey('errors', $responseContent);

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);
    }

    public function testContextTokenIsReplacedAfterChangingPassword(): void
    {
        $this->browser
            ->request(
                'POST',
                '/channel-api/account/change-password',
                [
                    'password' => 'contenaAdmin',
                    'newPassword' => 'new-password',
                    'newPasswordConfirm' => 'new-password',
                ]
            );

        $response = $this->browser->getResponse();

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        $oldContextExists = static::getContainer()->get(ChannelContextPersister::class)->load($this->contextToken, $this->ids->get('channel'));

        static::assertEmpty($oldContextExists);

        // Token is replaced
        static::assertNotSame($this->contextToken, $contextToken);

        $newContextExists = static::getContainer()->get(ChannelContextPersister::class)->load($contextToken, $this->ids->get('channel'), $this->memberId);

        static::assertNotEmpty($newContextExists);
    }
}
