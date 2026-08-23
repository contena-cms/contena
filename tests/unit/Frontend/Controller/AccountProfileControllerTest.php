<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\Channel\AbstractChangeEmailRoute;
use Contena\Core\System\Member\Channel\AbstractChangeMemberProfileRoute;
use Contena\Core\System\Member\Channel\AbstractChangePasswordRoute;
use Contena\Core\System\Member\Channel\AbstractDeleteMemberRoute;
use Contena\Core\System\Member\MemberEntity;
use Contena\Frontend\Controller\AccountProfileController;
use Contena\Frontend\Controller\FrontendController;
use Contena\Frontend\Page\Account\Overview\AccountOverviewPageLoader;
use Contena\Frontend\Page\Account\Profile\AccountProfilePageLoader;
use Contena\Tests\Unit\Frontend\Controller\Stub\AccountProfileControllerStub;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[CoversClass(AccountProfileController::class)]
class AccountProfileControllerTest extends TestCase
{
    private AbstractChangePasswordRoute&Stub $changePasswordRoute;

    private AccountProfileControllerStub $controller;

    protected function setUp(): void
    {
        $this->changePasswordRoute = static::createStub(AbstractChangePasswordRoute::class);

        $this->controller = new AccountProfileControllerStub(
            static::createStub(AccountOverviewPageLoader::class),
            static::createStub(AccountProfilePageLoader::class),
            static::createStub(AbstractChangeMemberProfileRoute::class),
            $this->changePasswordRoute,
            static::createStub(AbstractChangeEmailRoute::class),
            static::createStub(AbstractDeleteMemberRoute::class),
            static::createStub(LoggerInterface::class),
        );
    }

    public function testSavePasswordWithMissingPasswordParam(): void
    {
        $this->expectExceptionObject(RoutingException::missingRequestParameter('password'));

        $this->controller->savePassword(
            new RequestDataBag(),
            static::createStub(ChannelContext::class),
            new MemberEntity(),
            new Request(),
        );
    }

    public function testSavePasswordWithConstraintViolation(): void
    {
        $this->changePasswordRoute->method('change')->willThrowException(
            new ConstraintViolationException(new ConstraintViolationList(), []),
        );

        $this->controller->savePassword(
            $this->passwordDataBag(),
            static::createStub(ChannelContext::class),
            new MemberEntity(),
            new Request(),
        );

        static::assertSame('frontend.account.profile.page', $this->controller->forwardToRoute);
        static::assertTrue($this->controller->forwardToRouteAttributes['passwordFormViolation']);
        static::assertInstanceOf(ConstraintViolationException::class, $this->controller->forwardToRouteAttributes['formViolations']);
        static::assertSame(['account.passwordChangeNoSuccess'], $this->controller->flashBag[FrontendController::DANGER]);
    }

    public function testSavePasswordWithDefaultRedirect(): void
    {
        $this->controller->savePassword(
            $this->passwordDataBag(),
            static::createStub(ChannelContext::class),
            new MemberEntity(),
            new Request(),
        );

        static::assertArrayHasKey('frontend.account.profile.page', $this->controller->redirected);
        static::assertSame(['account.passwordChangeSuccess'], $this->controller->flashBag[FrontendController::SUCCESS]);
    }

    public function testSavePasswordWithCustomRedirect(): void
    {
        $this->controller->savePassword(
            $this->passwordDataBag(),
            static::createStub(ChannelContext::class),
            new MemberEntity(),
            new Request([], ['redirectTo' => 'frontend.home.page']),
        );

        static::assertArrayHasKey('frontend.home.page', $this->controller->redirected);
    }

    public function testSavePasswordWithForwardToParam(): void
    {
        $this->controller->savePassword(
            $this->passwordDataBag(),
            static::createStub(ChannelContext::class),
            new MemberEntity(),
            new Request([], ['forwardTo' => 'frontend.account.home.page']),
        );

        static::assertSame('frontend.account.home.page', $this->controller->forwardToRoute);
    }

    private function passwordDataBag(): RequestDataBag
    {
        return new RequestDataBag(['password' => new RequestDataBag([
            'newPassword' => 'newPassword123',
            'newPasswordConfirm' => 'newPassword123',
            'password' => 'oldPassword',
        ])]);
    }
}
