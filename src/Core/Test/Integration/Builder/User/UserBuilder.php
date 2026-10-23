<?php declare(strict_types=1);

namespace Contena\Core\Test\Integration\Builder\User;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestBuilderTrait;
use Contena\Core\Test\TestDefaults;

/**
 * @final
 * How to use:
 * $x = (new UserBuilder(new IdsCollection(), 'p1'))
 *          ->firstName('Max')
 *          ->lastName('Muster')
 *          ->group('standard')
 *          ->build();
 */
class UserBuilder
{
    use KernelTestBehaviour;
    use TestBuilderTrait;

    public string $id;

    protected string $firstName = 'Max';

    protected string $lastName = 'Mustermann';

    protected string $email = 'max@mustermann.com';

    protected string $userGroupId;

    protected string $defaultBillingAddressId;

    /**
     * @var array<string, mixed>
     */
    protected array $defaultBillingAddress = [];

    protected string $defaultShippingAddressId;

    /**
     * @var array<string, mixed>
     */
    protected array $addresses = [];

    /**
     * @var array<string, mixed>
     */
    protected array $group = [];

    /**
     * @var array<string, mixed>
     */
    protected array $salutation = [];

    public function __construct(
        IdsCollection $ids,
        protected string $userNumber,
        protected string $channelId = TestDefaults::CHANNEL,
        string $userGroup = 'user-group',
        string $billingAddress = 'default-address',
        string $shippingAddress = 'default-address'
    ) {
        $this->ids = $ids;
        $this->id = $ids->create($userNumber);
        $this->email = $this->id . '@example.com';
        $this->salutation = self::salutation($ids);

        $this->userGroup($userGroup);
        $this->defaultBillingAddress($billingAddress);
        $this->defaultShippingAddress($shippingAddress);
    }

    public function userNumber(string $userNumber): self
    {
        $this->userNumber = $userNumber;

        return $this;
    }

    public function firstName(string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function lastName(string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function userGroup(string $key): self
    {
        $this->userGroupId = $this->ids->get($key);
        $this->group = [
            'id' => $this->ids->get($key),
            'name' => $key,
        ];

        return $this;
    }

    /**
     * @param array<string, mixed> $customParams
     */
    public function defaultBillingAddress(string $key, array $customParams = []): self
    {
        $this->addAddress($key, $customParams);

        $defaultBillingAddress = $this->addresses;
        $defaultBillingAddress[$key]['id'] = $this->ids->get($key);
        $this->defaultBillingAddress = $defaultBillingAddress[$key];
        $this->defaultBillingAddressId = $this->ids->get($key);

        return $this;
    }

    /**
     * @param array<string, mixed> $customParams
     */
    public function defaultShippingAddress(string $key, array $customParams = []): self
    {
        $this->addAddress($key, $customParams);
        $this->defaultShippingAddressId = $this->ids->get($key);

        return $this;
    }

    /**
     * @param array<string, mixed> $customParams
     */
    public function addAddress(string $key, array $customParams = []): self
    {
        $address = \array_replace([
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'city' => 'Bielefeld',
            'salutation' => self::salutation($this->ids),
            'street' => 'Buchenweg 5',
            'zipcode' => '33062',
            'countryId' => $this->getCountry(),
        ], $customParams);

        $this->addresses[$key] = $address;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    private static function salutation(IdsCollection $ids): array
    {
        return [
            'id' => $ids->get('salutation'),
            'salutationKey' => 'salutation',
            'displayName' => 'test',
            'letterName' => 'test',
        ];
    }

    private static function connection(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }

    private function getCountry(): string
    {
        return self::connection()->fetchOne(
            'SELECT LOWER(HEX(country_id)) FROM channel_country WHERE channel_id = :id LIMIT 1',
            ['id' => Uuid::fromHexToBytes($this->channelId)]
        );
    }
}
