<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Contena\Core\Content\Newsletter\Channel\NewsletterSubscribeRoute;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Test\TestCaseBase\ChannelFunctionalTestBehaviour;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @internal
 */
class NewsletterControllerTest extends TestCase
{
    use ChannelFunctionalTestBehaviour;
    use FrontendControllerTestBehaviour;

    /**
     * @var array<string, mixed>
     */
    private array $userData = [];

    public function testRegisterNewsletterForUserDirect(): void
    {
        $browser = $this->login();
        $data = [
            'option' => 'direct',
        ];

        $browser->request(
            'POST',
            '/widgets/account/newsletter',
            $this->tokenize('frontend.account.newsletter', $data)
        );

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        static::assertSame(200, $response->getStatusCode());

        $repo = static::getContainer()->get('newsletter_recipient.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', 'nltest@example.com'));
        /** @var NewsletterRecipientEntity $recipientEntry */
        $recipientEntry = $repo->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertSame('direct', (string) $recipientEntry->getStatus());
        $this->validateRecipientData($recipientEntry);
    }

    public function testRegisterNewsletterForUserDoi(): void
    {
        $container = static::getContainer();

        $systemConfigService = $container->get(SystemConfigService::class);
        static::assertNotNull($systemConfigService);
        $systemConfigService->set('core.newsletter.doubleOptInRegistered', true);

        $browser = $this->login();
        $data = [
            'option' => 'subscribe',
        ];

        $browser->request(
            'POST',
            '/widgets/account/newsletter',
            $this->tokenize('frontend.account.newsletter', $data)
        );

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        static::assertSame(200, $response->getStatusCode());

        $repo = $container->get('newsletter_recipient.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', 'nltest@example.com'));
        $context = Context::createDefaultContext();
        /** @var NewsletterRecipientEntity $recipientEntry */
        $recipientEntry = $repo->search($criteria, $context)->getEntities()->first();

        $params = [
            'em' => Hasher::hash('nltest@example.com', 'sha1'),
            'hash' => $recipientEntry->getHash(),
        ];

        $browser->request(
            'GET',
            '/newsletter-subscribe?' . http_build_query($params)
        );

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        static::assertSame(200, $response->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', 'nltest@example.com'));
        /** @var NewsletterRecipientEntity $recipientEntry */
        $recipientEntry = $repo->search($criteria, $context)->getEntities()->first();

        static::assertSame(NewsletterSubscribeRoute::STATUS_OPT_IN, (string) $recipientEntry->getStatus());
        $this->validateRecipientData($recipientEntry);

        // Reset status and test again with redirect
        $repo->update([
            [
                'id' => $recipientEntry->getId(),
                'status' => NewsletterSubscribeRoute::STATUS_NOT_SET,
            ],
        ], $context);

        $params['redirectTo'] = 'frontend.home.page';

        $browser->request(
            'GET',
            '/newsletter-subscribe?' . http_build_query($params)
        );

        $response = $browser->getResponse();

        static::assertSame(302, $response->getStatusCode());
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/', $response->getTargetUrl());

        $session = $this->getSession();
        static::assertInstanceOf(Session::class, $session);
        $success = $session->getFlashBag()->get('success');

        static::assertNotCount(0, $success);
        static::assertSame($container->get('translator')->trans('newsletter.subscriptionCompleted'), $success[0]);
    }

    private function login(): KernelBrowser
    {
        $user = $this->createUser();

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => $user->getEmail(),
                'password' => 'contena',
            ])
        );
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return $browser;
    }

    private function createUser(): UserEntity
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $this->userData = [
            'id' => $userId,
            'channelId' => TestDefaults::CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => 'nltest@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'title' => 'Dr.',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => '12345',
        ];

        /** @var EntityRepository<UserCollection> $repo */
        $repo = static::getContainer()->get('user.repository');

        $repo->create([$this->userData], Context::createDefaultContext());

        $user = $repo->search(new Criteria([$userId]), Context::createDefaultContext())->getEntities()->first();

        static::assertNotNull($user);

        return $user;
    }

    private function validateRecipientData(NewsletterRecipientEntity $recipientEntry): void
    {
        static::assertSame($this->userData['email'], $recipientEntry->getEmail());
        static::assertSame($this->userData['salutationId'], $recipientEntry->getSalutationId());
        static::assertSame($this->userData['title'], $recipientEntry->getTitle());
        static::assertSame($this->userData['firstName'], $recipientEntry->getFirstName());
        static::assertSame($this->userData['lastName'], $recipientEntry->getLastName());
        static::assertSame($this->userData['defaultShippingAddress']['zipcode'], $recipientEntry->getZipCode());
        static::assertSame($this->userData['defaultShippingAddress']['city'], $recipientEntry->getCity());
        static::assertSame($this->userData['defaultShippingAddress']['street'], $recipientEntry->getStreet());
    }
}
