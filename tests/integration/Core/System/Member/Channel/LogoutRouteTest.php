<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ContextTokenResponse;
use Contena\Core\System\Member\Channel\LoginRoute;
use Contena\Core\System\Member\Channel\LogoutRoute;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('channel-api')]
class LogoutRouteTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('channel'),
        ]);
    }

    public function testNotLoggedIn(): void
    {
        $this->browser
            ->request(
                'POST',
                '/channel-api/account/logout',
            );

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame(RoutingException::CHANNEL_MEMBER_NOT_LOGGED_IN, $response['errors'][0]['code']);
    }

    public function testValidLogout(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $this->createMember($email);

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/login',
                [
                    'email' => $email,
                    'password' => 'contenaAdmin',
                ]
            );

        static::assertIsString($this->browser->getResponse()->getContent());

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/logout',
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/member'
            );

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
    }

    public function testLogoutDeletesOldMemberContext(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $this->createMember($email);

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/login',
                [
                    'email' => $email,
                    'password' => 'contenaAdmin',
                ]
            );

        static::assertIsString($this->browser->getResponse()->getContent());

        $response = $this->browser->getResponse();
        $currentMemberToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?: '';

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $currentMemberToken);

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/logout',
            );

        $memberIdWithOldToken = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT member_id FROM channel_api_context WHERE token = ?',
            [$currentMemberToken],
        );
        static::assertFalse($memberIdWithOldToken, 'The old token should be gone');
    }

    public function testLogoutRouteReturnsContextTokenResponse(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $memberId = $this->createMember($email);

        $contextToken = Random::getAlphanumericString(32);

        $channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(
            $contextToken,
            $this->ids->get('channel'),
            []
        );

        $request = new RequestDataBag(['email' => $email, 'password' => 'contenaAdmin']);
        $loginResponse = static::getContainer()->get(LoginRoute::class)->login($request, $channelContext);

        $member = static::getContainer()
            ->get('member.repository')
            ->search(new Criteria([$memberId]), Context::createDefaultContext())->getEntities()
            ->get($memberId);
        static::assertInstanceOf(MemberEntity::class, $member);
        $channelContext->assign([
            'token' => $loginResponse->getToken(),
            'member' => $member,
        ]);

        $logoutResponse = static::getContainer()->get(LogoutRoute::class)->logout(
            $channelContext,
            new RequestDataBag()
        );

        static::assertInstanceOf(ContextTokenResponse::class, $logoutResponse);
        static::assertNotSame($loginResponse->getToken(), $logoutResponse->getToken());
    }

    private function createMember(string $email): string
    {
        $memberId = Uuid::randomHex();

        static::getContainer()->get('member.repository')->create([[
            'id' => $memberId,
            'channelId' => $this->ids->get('channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'groupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max Member',
            'memberNumber' => $memberId,
            'active' => true,
        ]], Context::createDefaultContext());

        return $memberId;
    }
}
