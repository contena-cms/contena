<?php declare(strict_types=1);

namespace Contena\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Generator;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Demodata\DemodataContext;
use Contena\Core\Framework\Demodata\DemodataException;
use Contena\Core\Framework\Demodata\DemodataGeneratorInterface;
use Contena\Core\Framework\Demodata\DemodataService;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupCollection;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class UserGenerator implements DemodataGeneratorInterface
{
    /**
     * @var non-empty-list<string>
     */
    private array $salutationIds;

    private Generator $faker;

    /**
     * @internal
     *
     * @param EntityRepository<UserGroupCollection> $userGroupRepository
     */
    public function __construct(
        private readonly EntityWriterInterface $writer,
        private readonly Connection $connection,
        private readonly EntityRepository $userGroupRepository,
        private readonly NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        private readonly UserDefinition $userDefinition
    ) {
    }

    public function getDefinition(): string
    {
        return UserDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $this->faker = $context->getFaker();
        $this->createUsers($numberOfItems, $context);

        try {
            $this->createDefaultUser($context->getContext());
        } catch (\Exception $e) {
            $context->getConsole()->warning('Could not create default user: ' . $e->getMessage());
        }
    }

    private function createNetUserGroup(Context $context): string
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'displayGross' => false,
            'name' => 'Net price user group',
        ];

        $this->userGroupRepository->create([$data], $context);

        return $id;
    }

    private function createDefaultUser(Context $context): void
    {
        $id = Uuid::randomHex();
        $shippingAddressId = Uuid::randomHex();
        $billingAddressId = Uuid::randomHex();
        $salutationId = Uuid::fromBytesToHex($this->getRandomSalutationId());
        $countries = $this->connection->fetchFirstColumn('SELECT id FROM country WHERE active = 1');
        if ($countries === []) {
            throw DemodataException::wrongExecutionOrder();
        }
        $channelIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM channel');
        if ($channelIds === []) {
            throw DemodataException::wrongExecutionOrder();
        }

        $user = [
            'id' => $id,
            'userNumber' => '1337',
            'salutationId' => $salutationId,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => 'test@example.com',
            'password' => 'contena',
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'channelId' => $channelIds[array_rand($channelIds)],
            'defaultBillingAddressId' => $billingAddressId,
            'defaultShippingAddressId' => $shippingAddressId,
            'customFields' => [DemodataService::DEMODATA_CUSTOM_FIELDS_KEY => true],
            'addresses' => [
                [
                    'id' => $shippingAddressId,
                    'userId' => $id,
                    'countryId' => Uuid::fromBytesToHex($countries[array_rand($countries)]),
                    'salutationId' => $salutationId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
                [
                    'id' => $billingAddressId,
                    'userId' => $id,
                    'countryId' => Uuid::fromBytesToHex($countries[array_rand($countries)]),
                    'salutationId' => $salutationId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Bahnhofstraße 27',
                    'zipcode' => '10332',
                    'city' => 'Berlin',
                ],
            ],
        ];

        $writeContext = WriteContext::createFromContext($context);

        $this->writer->upsert($this->userDefinition, [$user], $writeContext);
    }

    private function createUsers(int $numberOfItems, DemodataContext $context): void
    {
        $writeContext = WriteContext::createFromContext($context->getContext());

        $context->getConsole()->progressStart($numberOfItems);

        $netUserGroupId = $this->createNetUserGroup($context->getContext());
        $userGroups = [TestDefaults::FALLBACK_CUSTOMER_GROUP, $netUserGroupId];
        $tags = $this->getTagIds();

        $channelIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM channel');
        if ($channelIds === []) {
            throw DemodataException::wrongExecutionOrder();
        }
        $countries = $this->connection->fetchFirstColumn('SELECT id FROM country WHERE active = 1');

        $payload = [];
        for ($i = 0; $i < $numberOfItems; ++$i) {
            $randomDate = $context->getFaker()->dateTimeBetween('-2 years');
            $id = Uuid::randomHex();
            $firstName = $context->getFaker()->firstName();
            $lastName = $context->getFaker()->format('lastName');
            $salutationId = Uuid::fromBytesToHex($this->getRandomSalutationId());
            $title = $this->getRandomTitle();

            $addresses = [];

            $aCount = random_int(2, 5);
            for ($x = 1; $x < $aCount; ++$x) {
                $addresses[] = [
                    'id' => Uuid::randomHex(),
                    'countryId' => Uuid::fromBytesToHex($context->getFaker()->randomElement($countries)),
                    'salutationId' => $salutationId,
                    'title' => $title,
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'street' => $context->getFaker()->format('streetName'),
                    'zipcode' => $context->getFaker()->format('postcode'),
                    'city' => $context->getFaker()->format('city'),
                ];
            }
            \assert($addresses !== []);

            $user = [
                'id' => $id,
                'userNumber' => $this->numberRangeValueGenerator->getValue('user', $context->getContext(), null),
                'salutationId' => $salutationId,
                'title' => $title,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $id . $context->getFaker()->format('safeEmail'),
                // use dummy hashed password, so not need to compute the hash for every user
                // password is `contena`
                'password' => '$2y$10$XFRhv2TdOz9GItRt6ZgHl.e/HpO5Mfea6zDNXI9Q8BasBRtWbqSTS',
                'groupId' => $userGroups[array_rand($userGroups)],
                'channelId' => $channelIds[array_rand($channelIds)],
                'defaultBillingAddressId' => $addresses[array_rand($addresses)]['id'],
                'defaultShippingAddressId' => $addresses[array_rand($addresses)]['id'],
                'addresses' => $addresses,
                'tags' => $this->getTags($tags),
                'createdAt' => $randomDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'customFields' => [DemodataService::DEMODATA_CUSTOM_FIELDS_KEY => true],
            ];

            $payload[] = $user;

            if (\count($payload) >= 100) {
                $this->writer->upsert($this->userDefinition, $payload, $writeContext);

                $context->getConsole()->progressAdvance(\count($payload));

                $payload = [];
            }
        }

        if ($payload !== []) {
            $this->writer->upsert($this->userDefinition, $payload, $writeContext);

            $context->getConsole()->progressAdvance(\count($payload));
        }

        $context->getConsole()->progressFinish();
    }

    private function getRandomTitle(): string
    {
        $titles = ['', 'Dr.', 'Dr. med.', 'Prof.', 'Prof. Dr.'];

        return $titles[array_rand($titles)];
    }

    /**
     * @param list<string> $tags
     *
     * @return list<array{id: string}>
     */
    private function getTags(array $tags): array
    {
        $tagAssignments = [];

        if ($tags !== []) {
            $chosenTags = $this->faker->randomElements($tags, $this->faker->numberBetween(1, \count($tags)));

            if ($chosenTags !== []) {
                $tagAssignments = array_map(
                    static fn (string $id) => ['id' => $id],
                    $chosenTags
                );
            }
        }

        return array_values($tagAssignments);
    }

    /**
     * @return list<string>
     */
    private function getTagIds(): array
    {
        return $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) as id FROM tag LIMIT 500');
    }

    private function getRandomSalutationId(): string
    {
        if (!isset($this->salutationIds)) {
            $salutationIds = $this->connection->fetchFirstColumn('SELECT id FROM salutation');
            if ($salutationIds === []) {
                throw DemodataException::wrongExecutionOrder();
            }
            $this->salutationIds = $salutationIds;
        }

        return $this->salutationIds[array_rand($this->salutationIds)];
    }
}
