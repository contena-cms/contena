<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\Channel\ContextSwitchRoute;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ContextTokenResponse;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Frontend\Controller\ContextController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[CoversClass(ContextController::class)]
class ContextControllerUnitTest extends TestCase
{
    public function testSwitchLangNoArgument(): void
    {
        $controller = new ContextController(
            static::createStub(ContextSwitchRoute::class),
            static::createStub(RequestStack::class),
            static::createStub(RouterInterface::class)
        );

        $this->expectExceptionObject(RoutingException::missingRequestParameter('languageId'));

        $controller->switchLanguage(new Request(), static::createStub(ChannelContext::class));
    }

    public function testSwitchLangNoString(): void
    {
        $controller = new ContextController(
            static::createStub(ContextSwitchRoute::class),
            static::createStub(RequestStack::class),
            static::createStub(RouterInterface::class)
        );

        $this->expectExceptionObject(RoutingException::invalidRequestParameter('languageId'));

        $controller->switchLanguage(
            new Request([], ['languageId' => 1]),
            static::createStub(ChannelContext::class)
        );
    }

    public function testSwitchLangNoValidUuid(): void
    {
        $controller = new ContextController(
            static::createStub(ContextSwitchRoute::class),
            static::createStub(RequestStack::class),
            static::createStub(RouterInterface::class)
        );

        $this->expectExceptionObject(RoutingException::invalidRequestParameter('languageId'));

        $controller->switchLanguage(
            new Request([], ['languageId' => 'noUuid']),
            static::createStub(ChannelContext::class)
        );
    }

    public function testSwitchLangNotFound(): void
    {
        $contextSwitchRoute = $this->createMock(ContextSwitchRoute::class);
        $contextSwitchRoute->expects($this->once())->method('switchContext')->willThrowException(
            new ConstraintViolationException(new ConstraintViolationList(), [])
        );
        $controller = new ContextController(
            $contextSwitchRoute,
            static::createStub(RequestStack::class),
            static::createStub(RouterInterface::class)
        );

        $notExistingLang = Uuid::randomHex();

        $this->expectExceptionObject(RoutingException::languageNotFound($notExistingLang));

        $controller->switchLanguage(
            new Request([], ['languageId' => $notExistingLang]),
            static::createStub(ChannelContext::class)
        );
    }

    public function testSwitchUserChange(): void
    {
        $language = new LanguageEntity();
        $language->setUniqueIdentifier(Uuid::randomHex());
        $scDomain = new ChannelDomainEntity();
        $scDomain->setUniqueIdentifier(Uuid::randomHex());
        $scDomain->setUrl('http://localhost');
        $language->setChannelDomains(new ChannelDomainCollection([$scDomain]));

        $routerMock = $this->createMock(RouterInterface::class);
        $routerMock->expects($this->once())->method('getContext')->willReturn(new RequestContext());
        $routerMock->expects($this->once())->method('generate')->willReturn('http://localhost');
        $requestStackMock = $this->createMock(RequestStack::class);
        $requestStackMock->expects($this->exactly(2))->method('getMainRequest')->willReturn(new Request());

        $contextSwitchRoute = $this->createMock(ContextSwitchRoute::class);
        $contextSwitchRoute->expects($this->once())->method('switchContext')->willReturn(
            new ContextTokenResponse(Uuid::randomHex(), 'http://localhost')
        );

        $controller = new ContextController(
            $contextSwitchRoute,
            $requestStackMock,
            $routerMock
        );

        $contextMock = static::createStub(ChannelContext::class);

        $controller->switchLanguage(
            new Request([], ['languageId' => Defaults::LANGUAGE_SYSTEM, 'redirectTo' => null]),
            $contextMock
        );
    }

    public function testSwitchRedirectToNotExistingTarget(): void
    {
        $language = new LanguageEntity();
        $language->setUniqueIdentifier(Uuid::randomHex());
        $scDomain = new ChannelDomainEntity();
        $scDomain->setUniqueIdentifier(Uuid::randomHex());
        $scDomain->setUrl('http://localhost');
        $language->setChannelDomains(new ChannelDomainCollection([$scDomain]));

        $routerMock = $this->createMock(RouterInterface::class);
        $routerMock->expects($this->once())->method('getContext')->willReturn(new RequestContext());
        $routerMock->expects($this->exactly(2))->method('generate')->willReturn('http://localhost');
        $requestStackMock = $this->createMock(RequestStack::class);
        $requestStackMock->expects($this->exactly(2))->method('getMainRequest')->willReturn(new Request());

        $contextSwitchRoute = $this->createMock(ContextSwitchRoute::class);
        $contextSwitchRoute->expects($this->once())->method('switchContext')->willReturn(
            new ContextTokenResponse(Uuid::randomHex(), 'http://localhost')
        );

        $controller = new ContextController(
            $contextSwitchRoute,
            $requestStackMock,
            $routerMock
        );

        $notExistingRedirectTo = 'frontend.homer.page';

        $contextMock = static::createStub(ChannelContext::class);

        $controller->switchLanguage(
            new Request([], ['languageId' => Defaults::LANGUAGE_SYSTEM, 'redirectTo' => $notExistingRedirectTo]),
            $contextMock
        );
    }

    public function testSwitchRedirectToExistingTarget(): void
    {
        $language = new LanguageEntity();
        $language->setUniqueIdentifier(Uuid::randomHex());
        $scDomain = new ChannelDomainEntity();
        $scDomain->setUniqueIdentifier(Uuid::randomHex());
        $scDomain->setUrl('http://localhost');
        $language->setChannelDomains(new ChannelDomainCollection([$scDomain]));

        $routerMock = $this->createMock(RouterInterface::class);
        $routerMock->expects($this->once())->method('getContext')->willReturn(new RequestContext());
        $routerMock->expects($this->exactly(2))->method('generate')->willReturn('http://localhost');
        $requestStackMock = $this->createMock(RequestStack::class);
        $requestStackMock->expects($this->exactly(2))->method('getMainRequest')->willReturn(new Request());

        $contextSwitchRoute = $this->createMock(ContextSwitchRoute::class);
        $contextSwitchRoute->expects($this->once())->method('switchContext')->willReturn(
            new ContextTokenResponse(Uuid::randomHex(), 'http://localhost')
        );

        $controller = new ContextController(
            $contextSwitchRoute,
            $requestStackMock,
            $routerMock
        );

        $existingRedirectTo = 'frontend.home.page';

        $contextMock = static::createStub(ChannelContext::class);

        $controller->switchLanguage(
            new Request([], ['languageId' => Defaults::LANGUAGE_SYSTEM, 'redirectTo' => $existingRedirectTo]),
            $contextMock
        );
    }
}
