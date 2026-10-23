<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Demodata\PersonalData;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\Demodata\PersonalData\CleanPersonalDataCommand;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 */
class CleanPersonalDataCommandTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->userRepository = static::getContainer()->get('user.repository');
        $this->clearTable('cart');
        $this->clearTable('user');
    }

    public function testCommandRemovesGuest(): void
    {
        $this->createGuest();

        static::assertCount(1, $this->fetchAllUsers());

        $input = new ArrayInput(['type' => 'guests'], $this->createInputDefinition());
        $this->getCommand()->run($input, new BufferedOutput());

        static::assertCount(0, $this->fetchAllUsers());
    }

    public function testCommandIsNoGuest(): void
    {
        $this->createGuest(false);

        static::assertCount(1, $this->fetchAllUsers());

        $input = new ArrayInput(['type' => 'guests'], $this->createInputDefinition());
        $this->getCommand()->run($input, new BufferedOutput());

        static::assertCount(1, $this->fetchAllUsers());
    }

    public function testCommandRemovesOnlyGuestsByDays(): void
    {
        $numberOfGuests = random_int(2, 6);
        $numberOfNoGuests = random_int(1, $numberOfGuests);

        for ($i = 0; $i < $numberOfGuests; ++$i) {
            $this->createGuest();
        }

        for ($i = 0; $i < $numberOfNoGuests; ++$i) {
            $this->createGuest(false);
        }

        $this->connection->executeStatement(
            'UPDATE user set created_at = :createdAt where guest = true limit 1',
            ['createdAt' => (new \DateTime())->modify('-14 Day')->format(Defaults::STORAGE_DATE_TIME_FORMAT)]
        );

        $input = new ArrayInput(['type' => 'guests', '--days' => 14], $this->createInputDefinition());
        $this->getCommand()->run($input, new BufferedOutput());

        static::assertCount($numberOfGuests + $numberOfNoGuests - 1, $this->fetchAllUsers());

        $input = new ArrayInput(['type' => 'guests'], $this->createInputDefinition());
        $this->getCommand()->run($input, new BufferedOutput());

        static::assertCount($numberOfNoGuests, $this->fetchAllUsers());
    }

    public function testCommandRemovesNoGuestBecauseOfOrder(): void
    {
        $id = $this->createGuest();
        $this->createOrder($id);

        static::assertCount(1, $this->fetchAllUsers());

        $input = new ArrayInput(['type' => 'guests'], $this->createInputDefinition());
        $this->getCommand()->run($input, new BufferedOutput());

        static::assertCount(1, $this->fetchAllUsers());
    }

    public function testCommandRemovesMultipleGuests(): void
    {
        $numberOfGuests = random_int(2, 5);

        for ($i = 0; $i < $numberOfGuests; ++$i) {
            $this->createGuest();
        }

        static::assertCount($numberOfGuests, $this->fetchAllUsers());

        $input = new ArrayInput(['type' => 'guests'], $this->createInputDefinition());
        $this->getCommand()->run($input, new BufferedOutput());

        static::assertCount(0, $this->fetchAllUsers());
    }

    public function testCommandRemovesNoGuestBecauseOfDays(): void
    {
        $this->createGuest();

        static::assertCount(1, $this->fetchAllUsers());

        $input = new ArrayInput(['type' => 'guests', '--days' => 5], $this->createInputDefinition());
        $this->getCommand()->run($input, new BufferedOutput());

        static::assertCount(1, $this->fetchAllUsers());
    }

    public function testCommandRemovesAll(): void
    {
        $this->createGuest();
        $this->createCartWithCreatedAtDateTime(new \DateTime());

        static::assertCount(1, $this->fetchAllUsers());
        static::assertCount(1, $this->fetchAllCarts());

        $input = new ArrayInput(['--all' => true], $this->createInputDefinition());
        $this->getCommand()->run($input, new BufferedOutput());

        static::assertCount(0, $this->fetchAllUsers());
        static::assertCount(0, $this->fetchAllCarts());
    }

    public function testCommandRemovesCart(): void
    {
        $this->createCartWithCreatedAtDateTime(new \DateTime());

        static::assertCount(1, $this->fetchAllCarts());

        $input = new ArrayInput(['type' => 'carts'], $this->createInputDefinition());
        $this->getCommand()->run($input, new BufferedOutput());

        static::assertCount(0, $this->fetchAllCarts());
    }

    public function testCommandRemovesMultipleCarts(): void
    {
        $numberOfCarts = random_int(2, 5);

        for ($i = 0; $i < $numberOfCarts; ++$i) {
            $this->createCartWithCreatedAtDateTime(new \DateTime());
        }

        static::assertCount($numberOfCarts, $this->fetchAllCarts());

        $input = new ArrayInput(['type' => 'carts'], $this->createInputDefinition());
        $this->getCommand()->run($input, new BufferedOutput());

        static::assertCount(0, $this->fetchAllCarts());
    }

    public function testCommandRemovesNoCartBecauseOfDays(): void
    {
        $this->createCartWithCreatedAtDateTime(new \DateTime());

        static::assertCount(1, $this->fetchAllCarts());

        $input = new ArrayInput(['type' => 'carts', '--days' => 5], $this->createInputDefinition());
        $this->getCommand()->run($input, new BufferedOutput());

        static::assertCount(1, $this->fetchAllCarts());
    }

    public function testCommandRemovesCartBecauseOfDays(): void
    {
        $this->createCartWithCreatedAtDateTime(new \DateTime());
        $this->createCartWithCreatedAtDateTime(new \DateTime('2018-10-10'));

        static::assertCount(2, $this->fetchAllCarts());

        $input = new ArrayInput(['type' => 'carts', '--days' => 5], $this->createInputDefinition());
        $this->getCommand()->run($input, new BufferedOutput());

        static::assertCount(1, $this->fetchAllCarts());
    }

    private function createOrder(
        string $userId
    ): void {
        $orderId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $order = [
            'id' => $orderId,
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderUser' => [
                'userId' => $userId,
                'email' => 'test@example.com',
                'salutationId' => $this->fetchFirstIdFromTable('salutation'),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
            ],
            'stateId' => $this->fetchFirstIdFromTable('state_machine_state'),
            'paymentMethodId' => $this->fetchFirstIdFromTable('payment_method'),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'channelId' => TestDefaults::CHANNEL,
            'billingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'salutationId' => $this->fetchFirstIdFromTable('salutation'),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                    'countryId' => $this->fetchFirstIdFromTable('country'),
                ],
            ],
            'lineItems' => [],
            'deliveries' => [],
            'context' => '{}',
            'payload' => '{}',
        ];

        static::getContainer()->get('order.repository')->upsert([$order], Context::createDefaultContext());
    }

    private function clearTable(string $table): void
    {
        $this->connection->executeStatement('DELETE FROM ' . $table);
    }

    private function createGuest(bool $isGuest = true): string
    {
        $id = Uuid::randomHex();
        $salutation = $this->fetchFirstIdFromTable('salutation');

        $address = [
            'firstName' => 'not',
            'lastName' => 'not',
            'city' => 'not',
            'street' => 'not',
            'zipcode' => 'not',
            'salutationId' => $salutation,
            'country' => ['name' => 'not'],
        ];

        $guest = [
            'id' => $id,
            'channelId' => TestDefaults::CHANNEL,
            'defaultShippingAddress' => $address,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'lastName' => 'not',
            'firstName' => Random::getAlphanumericString(20),
            'salutationId' => $salutation,
            'userNumber' => 'not',
            'guest' => $isGuest,
        ];

        $this->userRepository->upsert([$guest], Context::createDefaultContext());

        return $id;
    }

    private function createCartWithCreatedAtDateTime(\DateTime $dateTime): void
    {
        $cartData = [
            'token' => Uuid::randomHex(),
            'payload' => '',
            'rule_ids' => '[]',
            'created_at' => $dateTime->format('Y-m-d H:i:s'),
        ];

        $this->connection->insert('cart', $cartData);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAllUsers(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM user');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAllCarts(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM cart');
    }

    private function createInputDefinition(): InputDefinition
    {
        $type = new InputArgument('type', InputArgument::OPTIONAL);
        $days = new InputOption('days', null, InputOption::VALUE_REQUIRED);
        $all = new InputOption('all', null, InputOption::VALUE_NONE);

        return new InputDefinition([$type, $days, $all]);
    }

    private function fetchFirstIdFromTable(string $table): string
    {
        return Uuid::fromBytesToHex((string) $this->connection->fetchOne('SELECT id FROM ' . $table . ' LIMIT 1'));
    }

    private function getCommand(): CleanPersonalDataCommand
    {
        return new CleanPersonalDataCommand($this->connection, $this->userRepository, new NativeClock());
    }
}
