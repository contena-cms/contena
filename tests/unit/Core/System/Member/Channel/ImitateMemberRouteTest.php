<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\ContextTokenResponse;
use Contena\Core\System\Member\Channel\AbstractLogoutRoute;
use Contena\Core\System\Member\Channel\AccountService;
use Contena\Core\System\Member\Channel\ImitateMemberRoute;
use Contena\Core\System\Member\ImitateMemberTokenGenerator;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\Struct\ImitateMemberToken;
use Contena\Core\Test\Generator;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
#[CoversClass(ImitateMemberRoute::class)]
class ImitateMemberRouteTest extends TestCase
{
    public function testImitateMember(): void
    {
        $token = 'testToken';
        $tokenStruct = new ImitateMemberToken();
        $tokenStruct->memberId = Uuid::randomHex();
        $tokenStruct->iss = Uuid::randomHex();
        $tokenStruct->channelId = TestDefaults::CHANNEL;
        $channelContext = Generator::generateChannelContext();
        $channelContext->assign(['member' => null]);

        $imitateMemberTokenGenerator = $this->createMock(ImitateMemberTokenGenerator::class);
        $imitateMemberTokenGenerator
            ->expects($this->once())
            ->method('decode')
            ->with($token)
            ->willReturn($tokenStruct);

        $accountService = $this->createMock(AccountService::class);
        $accountService
            ->expects($this->once())
            ->method('loginById')
            ->with($tokenStruct->memberId, $channelContext)
            ->willReturn('newToken');

        $route = new ImitateMemberRoute(
            $accountService,
            $imitateMemberTokenGenerator,
            static::createStub(AbstractLogoutRoute::class),
            static::createStub(AbstractChannelContextFactory::class),
        );

        $dataBag = new RequestDataBag([
            ImitateMemberRoute::TOKEN => $token,
        ]);

        $response = $route->imitateMemberLogin($dataBag, $channelContext);

        static::assertSame('newToken', $response->getToken());
        static::assertSame($tokenStruct->iss, $channelContext->getImitatingUserId());
    }

    public function testImitateMemberWithLoggedInMember(): void
    {
        $token = 'testToken';
        $tokenStruct = new ImitateMemberToken();
        $tokenStruct->memberId = Uuid::randomHex();
        $tokenStruct->iss = Uuid::randomHex();
        $tokenStruct->channelId = TestDefaults::CHANNEL;
        $currentMember = new MemberEntity();
        $currentMember->setId(Uuid::randomHex());
        $channelContext = Generator::generateChannelContext(member: $currentMember);

        $imitateMemberTokenGenerator = $this->createMock(ImitateMemberTokenGenerator::class);
        $imitateMemberTokenGenerator
            ->expects($this->once())
            ->method('decode')
            ->with($token)
            ->willReturn($tokenStruct);

        $channelContextFactory = $this->createMock(AbstractChannelContextFactory::class);
        $channelContextFactory
            ->expects($this->once())
            ->method('create')
            ->with('loggedOutToken', TestDefaults::CHANNEL)
            ->willReturn($channelContext);

        $accountService = $this->createMock(AccountService::class);
        $accountService
            ->expects($this->once())
            ->method('loginById')
            ->with($tokenStruct->memberId, $channelContext)
            ->willReturn('newToken');

        $logoutRoute = $this->createMock(AbstractLogoutRoute::class);
        $logoutRoute
            ->expects($this->once())
            ->method('logout')
            ->willReturn(new ContextTokenResponse('loggedOutToken'));

        $route = new ImitateMemberRoute(
            $accountService,
            $imitateMemberTokenGenerator,
            $logoutRoute,
            $channelContextFactory,
        );

        $dataBag = new RequestDataBag([
            ImitateMemberRoute::TOKEN => $token,
        ]);

        $response = $route->imitateMemberLogin($dataBag, $channelContext);

        static::assertSame('newToken', $response->getToken());
        static::assertSame($tokenStruct->iss, $channelContext->getImitatingUserId());
    }
}
