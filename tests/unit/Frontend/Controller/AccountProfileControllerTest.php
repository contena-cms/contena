<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Channel\AbstractChangeUserProfileRoute;
use Contena\Core\System\User\Channel\AbstractChangeEmailRoute;
use Contena\Core\System\User\Channel\AbstractDeleteUserRoute;
use Contena\Core\System\User\Channel\ChangePasswordRoute;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Controller\AccountProfileController;
use Contena\Frontend\Controller\FrontendController;
use Contena\Frontend\Page\Account\Overview\AccountOverviewPageLoader;
use Contena\Frontend\Page\Account\Profile\AccountProfilePage;
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
    private AccountProfilePageLoader&Stub $profilePageLoader;

    private AbstractChangeUserProfileRoute&Stub $changeUserProfileRoute;

    private ChangePasswordRoute&Stub $changePasswordRoute;

    private AccountProfileControllerStub $controller;

    protected function setUp(): void
    {
        $this->profilePageLoader = static::createStub(AccountProfilePageLoader::class);
        $this->changeUserProfileRoute = static::createStub(AbstractChangeUserProfileRoute::class);
        $this->changePasswordRoute = static::createStub(ChangePasswordRoute::class);

        $this->controller = new AccountProfileControllerStub(
            static::createStub(AccountOverviewPageLoader::class),
            $this->profilePageLoader,
            $this->changeUserProfileRoute,
            $this->changePasswordRoute,
            static::createStub(AbstractChangeEmailRoute::class),
            static::createStub(AbstractDeleteUserRoute::class),
            static::createStub(LoggerInterface::class),
        );
    }

    public function testProfileOverviewKeepsTheRedirectTarget(): void
    {
        $this->controller->profileOverview(
            new Request(['redirectTo' => 'frontend.checkout.confirm.page']),
            static::createStub(ChannelContext::class)
        );

        static::assertSame('@Frontend/frontend/page/account/profile/index.html.twig', $this->controller->recorder()->renderFrontendView);
        static::assertSame('frontend.checkout.confirm.page', $this->controller->recorder()->renderFrontendParameters['redirectTo']);
    }

    public function testProfileOverviewRendersSubmittedProfileData(): void
    {
        $this->profilePageLoader->method('load')->willReturn(new AccountProfilePage());
        $submittedData = new RequestDataBag(['accountType' => 'business', 'company' => 'Contena']);

        $request = new Request();
        $request->attributes->set('profileFormData', $submittedData);

        $this->controller->profileOverview($request, static::createStub(ChannelContext::class));

        static::assertSame('@Frontend/frontend/page/account/profile/index.html.twig', $this->controller->recorder()->renderFrontendView);
        static::assertSame($submittedData, $this->controller->recorder()->renderFrontendParameters['profileFormData']);
    }

    public function testSaveProfileWithDefaultRedirect(): void
    {
        $this->controller->saveProfile(
            new RequestDataBag(),
            static::createStub(ChannelContext::class),
            new UserEntity(),
            new Request()
        );

        static::assertArrayHasKey('frontend.account.profile.page', $this->controller->recorder()->redirected);
        static::assertSame(['account.profileUpdateSuccess'], $this->controller->recorder()->flashBag[FrontendController::SUCCESS]);
    }

    public function testSaveProfileReturnsToTheRedirectTarget(): void
    {
        $this->controller->saveProfile(
            new RequestDataBag(),
            static::createStub(ChannelContext::class),
            new UserEntity(),
            new Request([], ['redirectTo' => 'frontend.checkout.confirm.page'])
        );

        static::assertArrayHasKey('frontend.checkout.confirm.page', $this->controller->recorder()->redirected);
    }

    public function testSaveProfileWithConstraintViolationForwardsSubmittedData(): void
    {
        $this->changeUserProfileRoute->method('change')->willThrowException(
            new ConstraintViolationException(new ConstraintViolationList(), [])
        );
        $submittedData = new RequestDataBag(['accountType' => 'business', 'company' => 'Contena', 'vatIds' => ['DE123']]);

        $this->controller->saveProfile(
            $submittedData,
            static::createStub(ChannelContext::class),
            new UserEntity(),
            new Request([], ['redirectTo' => 'frontend.checkout.confirm.page'])
        );

        static::assertSame('frontend.account.profile.page', $this->controller->recorder()->forwardToRoute);
        static::assertInstanceOf(ConstraintViolationException::class, $this->controller->recorder()->forwardToRouteAttributes['formViolations']);
        static::assertSame($submittedData, $this->controller->recorder()->forwardToRouteAttributes['profileFormData']);
        static::assertSame([], $this->controller->recorder()->redirected);
    }

    public function testSavePasswordWithMissingPasswordParam(): void
    {
        $this->expectExceptionObject(RoutingException::missingRequestParameter('password'));

        $this->controller->savePassword(
            new RequestDataBag(),
            static::createStub(ChannelContext::class),
            new UserEntity(),
            new Request()
        );
    }

    public function testSavePasswordWithConstraintViolation(): void
    {
        $this->changePasswordRoute->method('change')->willThrowException(
            new ConstraintViolationException(new ConstraintViolationList(), [])
        );

        $this->controller->savePassword(
            $this->passwordDataBag(),
            static::createStub(ChannelContext::class),
            new UserEntity(),
            new Request()
        );

        static::assertSame('frontend.account.profile.page', $this->controller->recorder()->forwardToRoute);
        static::assertTrue($this->controller->recorder()->forwardToRouteAttributes['passwordFormViolation']);
        static::assertInstanceOf(ConstraintViolationException::class, $this->controller->recorder()->forwardToRouteAttributes['formViolations']);
        static::assertSame(['account.passwordChangeNoSuccess'], $this->controller->recorder()->flashBag[FrontendController::DANGER]);
    }

    public function testSavePasswordWithDefaultRedirect(): void
    {
        $this->controller->savePassword(
            $this->passwordDataBag(),
            static::createStub(ChannelContext::class),
            new UserEntity(),
            new Request()
        );

        static::assertArrayHasKey('frontend.account.profile.page', $this->controller->recorder()->redirected);
        static::assertSame(['account.passwordChangeSuccess'], $this->controller->recorder()->flashBag[FrontendController::SUCCESS]);
    }

    public function testSavePasswordWithCustomRedirect(): void
    {
        $this->controller->savePassword(
            $this->passwordDataBag(),
            static::createStub(ChannelContext::class),
            new UserEntity(),
            new Request([], ['redirectTo' => 'frontend.home.page'])
        );

        static::assertArrayHasKey('frontend.home.page', $this->controller->recorder()->redirected);
    }

    public function testSavePasswordWithForwardToParam(): void
    {
        $this->controller->savePassword(
            $this->passwordDataBag(),
            static::createStub(ChannelContext::class),
            new UserEntity(),
            new Request([], ['forwardTo' => 'frontend.account.home.page'])
        );

        static::assertSame('frontend.account.home.page', $this->controller->recorder()->forwardToRoute);
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
