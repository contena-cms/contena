<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Salutation\SalutationDefinition;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class UserSalutationSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->connection = KernelLifecycleManager::getConnection();

        $this->userRepository = static::getContainer()->get('user.repository');
    }

    public function testSetDefaultSalutationWithExistingNotSpecifiedSalutation(): void
    {
        $salutations = $this->connection->fetchAllKeyValue('SELECT salutation_key, id FROM salutation');
        static::assertArrayHasKey(SalutationDefinition::NOT_SPECIFIED, $salutations);

        $this->createUser();

        /** @var UserEntity $user */
        $user = $this->userRepository->search(
            new Criteria([$this->ids->get('user')]),
            Context::createDefaultContext()
        )->getEntities()->first();

        static::assertNotNull($user->getSalutationId());
    }

    public function testSetDefaultSalutationToNotSpecifiedWithoutExistingSalutation(): void
    {
        $this->connection->executeStatement(
            '
					DELETE FROM salutation WHERE salutation_key = :salutationKey
				',
            ['salutationKey' => SalutationDefinition::NOT_SPECIFIED]
        );

        $salutations = $this->connection->fetchAllKeyValue('SELECT salutation_key, id FROM salutation');
        static::assertArrayNotHasKey(SalutationDefinition::NOT_SPECIFIED, $salutations);

        $this->createUser();

        /** @var UserEntity $user */
        $user = $this->userRepository->search(
            new Criteria([$this->ids->get('user')]),
            Context::createDefaultContext()
        )->getEntities()->first();

        static::assertNull($user->getSalutationId());
    }

    private function createUser(): void
    {
        $userId = $this->ids->create('user');
        $addressId = Uuid::randomHex();

        $user = [
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
            'email' => Uuid::randomHex() . '@example.com',
            'password' => Uuid::randomHex(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'guest' => false,
            'salutationId' => null,
            'userNumber' => '12345',
        ];

        $this->userRepository->create([$user], Context::createDefaultContext());
    }
}
