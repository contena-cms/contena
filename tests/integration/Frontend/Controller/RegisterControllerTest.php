<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\ChannelRequest;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Controller\RegisterController;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class RegisterControllerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ChannelContext $channelContext;

    protected function setUp(): void
    {
        $this->channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::CHANNEL,
        );
    }

    public function testRegister(): void
    {
        $data = $this->getRegistrationData();
        $request = $this->createRequest();

        $response = static::getContainer()->get(RegisterController::class)->register($request, $data, $this->channelContext);

        static::assertSame(200, $response->getStatusCode());
        static::assertCount(1, $this->findMembersByEmail($data->getString('email')));
        static::assertTrue($request->attributes->has(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT));
    }

    public function testDoubleSubmittedRegistrationCreatesOnlyOneMember(): void
    {
        $registerController = static::getContainer()->get(RegisterController::class);

        $data = $this->getRegistrationData();
        $email = $data->getString('email');
        $consumedToken = $this->channelContext->getToken();

        $firstRequest = $this->createMainRequest();
        $registerController->register($firstRequest, $data, $this->channelContext);

        static::assertNotSame($consumedToken, $this->channelContext->getToken());
        static::assertTrue($firstRequest->attributes->has(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT));

        $duplicateContext = static::getContainer()->get(ChannelContextFactory::class)
            ->create($consumedToken, TestDefaults::CHANNEL);

        $otherEmail = 'erika.musterfrau@example.com';
        $duplicateRequest = $this->createMainRequest();
        $duplicateResponse = $registerController->register(
            $duplicateRequest,
            $this->getRegistrationData($otherEmail),
            $duplicateContext,
        );

        static::assertSame(200, $duplicateResponse->getStatusCode());
        static::assertCount(1, $this->findMembersByEmail($email));
        static::assertCount(0, $this->findMembersByEmail($otherEmail));
        static::assertFalse(
            $duplicateRequest->attributes->has(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT),
            'the duplicate submission must be suppressed instead of registering again',
        );
    }

    public function testEveryStaleResubmissionOfASpentTokenIsSuppressed(): void
    {
        $registerController = static::getContainer()->get(RegisterController::class);
        $contextFactory = static::getContainer()->get(ChannelContextFactory::class);

        $data = $this->getRegistrationData();
        $email = $data->getString('email');
        $consumedToken = $this->channelContext->getToken();

        $registerController->register($this->createMainRequest(), $data, $this->channelContext);

        static::assertCount(1, $this->findMembersByEmail($email));

        for ($resubmission = 0; $resubmission < 2; ++$resubmission) {
            $staleContext = $contextFactory->create($consumedToken, TestDefaults::CHANNEL);
            $staleRequest = $this->createMainRequest();

            $registerController->register($staleRequest, $this->getRegistrationData(), $staleContext);

            static::assertFalse(
                $staleRequest->attributes->has(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT),
                'resubmission ' . ($resubmission + 1) . ' must be suppressed',
            );
        }

        static::assertCount(1, $this->findMembersByEmail($email));
    }

    public function testDoubleSubmittedRegistrationWithDoubleOptInCreatesOnlyOneMember(): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.loginRegistration.doubleOptInRegistration', true, TestDefaults::CHANNEL);

        $registerController = static::getContainer()->get(RegisterController::class);

        $data = $this->getRegistrationData('double-submit-opt-in@example.com');
        $email = $data->getString('email');
        $consumedToken = $this->channelContext->getToken();

        $registerController->register($this->createMainRequest(), $data, $this->channelContext);

        static::assertSame($consumedToken, $this->channelContext->getToken());
        static::assertCount(1, $this->findMembersByEmail($email));

        $registerController->register($this->createMainRequest(), $this->getRegistrationData($email), $this->channelContext);

        static::assertCount(1, $this->findMembersByEmail($email));
    }

    public function testSuppressedDoubleSubmitLeavesTheDuplicateSessionAnonymous(): void
    {
        $registerController = static::getContainer()->get(RegisterController::class);

        $consumedToken = $this->channelContext->getToken();

        $firstRequest = $this->createMainRequest();
        $registerController->register($firstRequest, $this->getRegistrationData(), $this->channelContext);

        $winnerToken = $this->channelContext->getToken();
        static::assertSame($winnerToken, $firstRequest->getSession()->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $duplicateRequest = $this->createMainRequest();
        $duplicateRequest->getSession()->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $consumedToken);

        $duplicateContext = static::getContainer()->get(ChannelContextFactory::class)
            ->create($consumedToken, TestDefaults::CHANNEL);

        $registerController->register($duplicateRequest, $this->getRegistrationData(), $duplicateContext);

        static::assertFalse(
            $duplicateRequest->attributes->has(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT),
            'the duplicate submission must be suppressed instead of registering again',
        );

        static::assertSame($consumedToken, $duplicateRequest->getSession()->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNull($duplicateRequest->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testRejectedRegistrationDoesNotConsumeTheContextToken(): void
    {
        $registerController = static::getContainer()->get(RegisterController::class);

        $incompleteData = $this->getRegistrationData('rejected-registration@example.com');
        $incompleteData->set('name', '');

        $email = $incompleteData->getString('email');
        $token = $this->channelContext->getToken();

        $rejectedRequest = $this->createMainRequest();
        $registerController->register($rejectedRequest, $incompleteData, $this->channelContext);

        static::assertFalse($rejectedRequest->attributes->has(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT));
        static::assertCount(0, $this->findMembersByEmail($email));
        static::assertSame($token, $this->channelContext->getToken(), 'a rejected registration must not rotate the token');

        $registerController->register($this->createMainRequest(), $this->getRegistrationData($email), $this->channelContext);

        static::assertCount(1, $this->findMembersByEmail($email));
        static::assertNotSame($token, $this->channelContext->getToken());
    }

    public function testRegisterWithDoubleOptIn(): void
    {
        static::getContainer()->get(SystemConfigService::class)->set(
            'core.loginRegistration.doubleOptInRegistration',
            true,
        );

        $data = $this->getRegistrationData('double-opt-in@example.com');
        $response = static::getContainer()->get(RegisterController::class)->register(
            $this->createRequest(),
            $data,
            $this->channelContext,
        );

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/account/register', $response->getTargetUrl());

        $member = $this->findMembersByEmail($data->getString('email'))->first();
        static::assertInstanceOf(MemberEntity::class, $member);
        static::assertTrue($member->getDoubleOptInRegistration());
    }

    private function createRequest(): Request
    {
        $request = new Request();
        $request->setSession($this->getSession());
        $request->request->add(['errorRoute' => 'frontend.account.register.page']);
        $request->attributes->add([
            '_route' => 'frontend.account.register.page',
            ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID],
            PlatformRequest::ATTRIBUTE_CHANNEL_ID => TestDefaults::CHANNEL,
            RequestTransformer::FRONTEND_URL => 'http://localhost',
        ]);

        static::getContainer()->get('request_stack')->push($request);

        return $request;
    }

    private function createMainRequest(): Request
    {
        $requestStack = static::getContainer()->get('request_stack');

        while ($requestStack->getMainRequest() !== null) {
            $requestStack->pop();
        }

        return $this->createRequest();
    }

    private function getRegistrationData(string $email = 'max.mustermann@example.com'): RequestDataBag
    {
        return new RequestDataBag([
            'email' => $email,
            'emailConfirmation' => $email,
            'name' => 'Max Mustermann',
            'phoneNumber' => '123456789',
            'password' => 'contenaAdmin',
        ]);
    }

    private function findMembersByEmail(string $email): MemberCollection
    {
        /** @var EntityRepository<MemberCollection> $repository */
        $repository = static::getContainer()->get('member.repository');

        return $repository->search(
            new Criteria()->addFilter(new EqualsFilter('email', $email)),
            Context::createDefaultContext(),
        )->getEntities();
    }
}
