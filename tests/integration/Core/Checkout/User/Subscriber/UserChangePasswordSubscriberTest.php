<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Subscriber;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class UserChangePasswordSubscriberTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use ChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));

        $this->userRepository = static::getContainer()->get('user.repository');
    }

    public function testClearLegacyWhenUserChangePassword(): void
    {
        $email = Uuid::randomHex() . '@contena.cn';
        $password = 'ThisIsNewPassword';

        $newPassword = Uuid::randomHex();
        $userId = $this->createUserWithLegacyPassword($email, $password);

        $context = Context::createDefaultContext();

        $this->getBrowser()->jsonRequest(
            'PATCH',
            '/api/user/' . $userId,
            ['password' => $newPassword]
        );

        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $userId));

        /** @var UserEntity $user */
        $user = $this->userRepository->search($criteria, $context)->getEntities()->first();

        static::assertNotNull($user->getPassword());
        static::assertNull($user->getLegacyPassword());
        static::assertNull($user->getLegacyEncoder());

        $this->loginUser($email, $newPassword);
    }

    public function testNotClearLegacyDataWhenUserNotChangedPassword(): void
    {
        $email = Uuid::randomHex() . '@contena.cn';
        $password = 'ThisIsNewPassword';

        $userId = $this->createUserWithLegacyPassword($email, $password);
        $context = Context::createDefaultContext();

        $this->getBrowser()->jsonRequest(
            'PATCH',
            '/api/user/' . $userId,
            ['firstName' => 'Test']
        );

        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $userId));

        /** @var UserEntity $user */
        $user = $this->userRepository->search($criteria, $context)->getEntities()->first();

        static::assertNull($user->getPassword());
        static::assertNotNull($user->getLegacyPassword());
        static::assertNotNull($user->getLegacyEncoder());

        $this->loginUser($email, $password);
    }

    private function loginUser(string $email, string $password): void
    {
        $this->browser
            ->jsonRequest(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => $password,
                ]
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);
    }

    private function createUserWithLegacyPassword(string $email, string $password): string
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'channelId' => TestDefaults::CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schoöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $email,
            'password' => null,
            'legacyPassword' => Hasher::hash($password, 'md5'),
            'legacyEncoder' => 'Md5',
            'firstName' => 'encryption',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => '12345',
        ];

        static::getContainer()->get('user.repository')->create([$user], Context::createDefaultContext());

        return $userId;
    }
}
