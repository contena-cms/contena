<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\User\Api\ConvertGuestController;
use Contena\Core\System\User\Channel\AbstractConvertGuestRoute;
use Contena\Core\System\User\Channel\AbstractSendPasswordRecoveryMailRoute;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ConvertGuestController::class)]
class ConvertGuestControllerTest extends TestCase
{
    public function testConvertWithPasswordDoesNotSendRecoveryMail(): void
    {
        $user = $this->createUser();

        $repository = static::createStub(EntityRepository::class);

        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('getEntities')
            ->willReturn(new UserCollection([$user]));

        $repository->method('search')
            ->willReturn($searchResult);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')
            ->willReturn('context-token');

        $channelContext = static::createStub(ChannelContext::class);

        $contextService = static::createMock(ChannelContextServiceInterface::class);
        $contextService->expects($this->once())
            ->method('get')
            ->with(static::isInstanceOf(ChannelContextServiceParameters::class))
            ->willReturn($channelContext);

        $convertRoute = static::createMock(AbstractConvertGuestRoute::class);
        $convertRoute->expects($this->once())
            ->method('convertGuest')
            ->with(
                static::callback(function (RequestDataBag $bag) {
                    return $bag->get('password') === 'my-secret';
                }),
                $channelContext,
                $user
            );

        $recoveryRoute = static::createMock(AbstractSendPasswordRecoveryMailRoute::class);
        $recoveryRoute->expects($this->never())
            ->method('sendRecoveryMail');

        $controller = new ConvertGuestController(
            $repository,
            $contextService,
            $convertRoute,
            $recoveryRoute,
            $connection
        );

        $request = new Request(
            request: [
                'password' => 'my-secret',
            ]
        );

        $request->headers->set(
            PlatformRequest::HEADER_LANGUAGE_ID,
            'language-id'
        );

        $context = Context::createDefaultContext();

        $controller->convert(
            $request,
            $context,
            $user->getId(),
        );
    }

    public function testUserNotFound(): void
    {
        $repository = static::createStub(EntityRepository::class);

        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new UserCollection());

        $repository->method('search')->willReturn($searchResult);

        $controller = new ConvertGuestController(
            $repository,
            static::createStub(ChannelContextServiceInterface::class),
            static::createStub(AbstractConvertGuestRoute::class),
            static::createStub(AbstractSendPasswordRecoveryMailRoute::class),
            static::createStub(Connection::class)
        );

        $this->expectException(UserException::class);

        $controller->convert(
            new Request(),
            Context::createDefaultContext(),
            'user-id'
        );
    }

    public function testConvertWithoutPasswordSendsRecoveryMail(): void
    {
        $user = $this->createUser();

        $repository = static::createStub(EntityRepository::class);

        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new UserCollection([$user]));

        $repository->method('search')->willReturn($searchResult);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('context-token');

        $domain = new ChannelDomainEntity();
        $domain->setUniqueIdentifier(Uuid::randomHex());
        $domain->setLanguageId(Uuid::randomHex());
        $domain->setUrl('https://contena.test');

        $domains = new ChannelDomainCollection([$domain]);
        $channel = $this->createChannel($domains);
        $channelContext = $this->createChannelContext($user, $channel);

        $contextService = static::createMock(ChannelContextServiceInterface::class);
        $contextService->expects($this->once())
            ->method('get')
            ->willReturn($channelContext);

        $convertGuestRoute = static::createMock(AbstractConvertGuestRoute::class);
        $convertGuestRoute->expects($this->once())
            ->method('convertGuest');

        $sendPasswordRecoveryMailRoute = static::createMock(AbstractSendPasswordRecoveryMailRoute::class);
        $sendPasswordRecoveryMailRoute
            ->expects($this->once())
            ->method('sendRecoveryMail')
            ->with(
                static::callback(function (RequestDataBag $data): bool {
                    return $data->get('email') === 'test@example.com'
                        && $data->get('frontendUrl') === 'https://contena.test';
                }),
                static::identicalTo($channelContext)
            );

        $controller = new ConvertGuestController(
            $repository,
            $contextService,
            $convertGuestRoute,
            $sendPasswordRecoveryMailRoute,
            $connection
        );

        $request = new Request();
        $request->headers->set(
            PlatformRequest::HEADER_LANGUAGE_ID,
            Uuid::randomHex()
        );

        $controller->convert(
            $request,
            Context::createDefaultContext(),
            $user->getUniqueIdentifier()
        );
    }

    public function testConvertThrowsExceptionWhenNoChannelDomainExists(): void
    {
        $user = $this->createUser();
        $repository = static::createStub(EntityRepository::class);

        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new UserCollection([$user]));

        $repository->method('search')->willReturn($searchResult);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('context-token');

        $channel = $this->createChannel();
        $channelContext = $this->createChannelContext($user, $channel);

        $contextService = static::createStub(ChannelContextServiceInterface::class);
        $contextService->method('get')->willReturn($channelContext);

        $convertGuestRoute = static::createMock(AbstractConvertGuestRoute::class);
        $convertGuestRoute->expects($this->once())
            ->method('convertGuest');

        $sendPasswordRecoveryMailRoute = static::createMock(AbstractSendPasswordRecoveryMailRoute::class);
        $sendPasswordRecoveryMailRoute->expects($this->never())
            ->method('sendRecoveryMail');

        $controller = new ConvertGuestController(
            $repository,
            $contextService,
            $convertGuestRoute,
            $sendPasswordRecoveryMailRoute,
            $connection
        );

        $this->expectException(UserException::class);

        $request = new Request();

        $controller->convert(
            $request,
            Context::createDefaultContext(),
            $user->getUniqueIdentifier()
        );
    }

    private function createUser(): UserEntity
    {
        $user = new UserEntity();
        $user->setId(Uuid::randomHex());
        $user->setUniqueIdentifier(Uuid::randomHex());
        $user->setChannelId(Uuid::randomHex());
        $user->setEmail('test@example.com');
        $user->setLanguageId(Uuid::randomHex());

        return $user;
    }

    private function createChannel(?ChannelDomainCollection $domains = null): ChannelEntity
    {
        $channel = new ChannelEntity();
        $channel->setId(Uuid::randomHex());
        $channel->setUniqueIdentifier(Uuid::randomHex());
        if ($domains) {
            $channel->setDomains($domains);
        }

        return $channel;
    }

    private function createChannelContext(UserEntity $user, ChannelEntity $channel): ChannelContext
    {
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getUser')->willReturn($user);
        $channelContext->method('getChannel')->willReturn($channel);

        return $channelContext;
    }
}
