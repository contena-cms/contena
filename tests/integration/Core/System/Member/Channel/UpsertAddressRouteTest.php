<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Member\Aggregate\MemberAddress\MemberAddressCollection;
use Contena\Core\System\Member\Aggregate\MemberAddress\MemberAddressEntity;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Group('channel-api')]
class UpsertAddressRouteTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<MemberAddressCollection>
     */
    private EntityRepository $addressRepository;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->ids->set('country', $this->getCountryIdByIsoCode('CN'));
        $this->ids->set('region', $this->getRegionId($this->ids->get('country')));

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('channel'),
            'countryId' => $this->ids->get('country'),
            'countries' => [['id' => $this->ids->get('country')]],
        ]);
        $this->addressRepository = static::getContainer()->get('member_address.repository');

        $email = Uuid::randomHex() . '@example.com';
        $this->createMember($email);

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/login',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'email' => $email,
                    'password' => 'contenaAdmin',
                ], \JSON_THROW_ON_ERROR)
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }

    public function testCreateAddress(): void
    {
        $data = [
            'firstName' => "\nMax\t",
            'lastName' => "\rMember ",
            'street' => "\t Example Street 1 \n",
            'city' => "\rBeijing\n",
            'zipcode' => " 100000\t",
            'countryId' => $this->ids->get('country'),
            'regionId' => $this->ids->get('region'),
            'title' => "\tDr.\n",
            'phoneNumber' => "\t123456\n",
            'additionalAddressLine1' => '        Building 1         ',
            'additionalAddressLine2' => "    Floor 2\r",
        ];

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/address',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($data, \JSON_THROW_ON_ERROR)
            );

        $response = $this->browser->getResponse();
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertArrayHasKey('id', $content);
        static::assertSame('Max', $content['firstName']);
        static::assertSame('Member', $content['lastName']);
        static::assertSame('Example Street 1', $content['street']);
        static::assertSame('Beijing', $content['city']);
        static::assertSame('100000', $content['zipcode']);
        static::assertSame($this->ids->get('country'), $content['countryId']);
        static::assertSame($this->ids->get('region'), $content['regionId']);
        static::assertSame('Building 1', $content['additionalAddressLine1']);
        static::assertSame('Floor 2', $content['additionalAddressLine2']);

        // Check existence
        $address = $this->addressRepository->search(new Criteria([$content['id']]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(MemberAddressEntity::class, $address);
        static::assertSame($this->ids->get('region'), $address->getRegionId());
        static::assertSame('Building 1', $address->getAdditionalAddressLine1());
        static::assertSame('Floor 2', $address->getAdditionalAddressLine2());
    }

    public function testRequestWithNoParameters(): void
    {
        $this->browser
            ->request(
                'POST',
                '/channel-api/account/address'
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertGreaterThanOrEqual(1, is_countable($response['errors']) ? \count($response['errors']) : 0);
    }

    public function testUpdateExistingAddress(): void
    {
        $data = [
            'firstName' => 'Max',
            'lastName' => 'Member',
            'street' => 'Example Street 1',
            'city' => 'Beijing',
            'zipcode' => '100000',
            'countryId' => $this->ids->get('country'),
            'regionId' => $this->ids->get('region'),
        ];

        $this->browser->request('POST', '/channel-api/account/address', $data);
        $created = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $addressId = $created['id'];

        $this->addressRepository->update([
            [
                'id' => $addressId,
                'customFields' => ['initialCustomField' => 'initialValueShouldStay'],
            ],
        ], Context::createDefaultContext());

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/list-address'
            );

        $address = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['elements'][0];
        $address['firstName'] = __FUNCTION__;
        $address['additionalAddressLine1'] = 'Building 2';
        $address['customFields'] = ['randomCustomField' => 'randomValue'];

        // Update
        $this->browser
            ->request(
                'PATCH',
                '/channel-api/account/address/' . $addressId,
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($address, \JSON_THROW_ON_ERROR)
            );

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());

        // Verify
        $updatedAddress = $this->addressRepository->search(new Criteria([$addressId]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(MemberAddressEntity::class, $updatedAddress);
        static::assertSame(__FUNCTION__, $updatedAddress->getFirstName());
        static::assertSame('Building 2', $updatedAddress->getAdditionalAddressLine1());
        static::assertSame($this->ids->get('region'), $updatedAddress->getRegionId());
        static::assertSame('initialValueShouldStay', $updatedAddress->getCustomFieldsValue('initialCustomField'));
        static::assertNull($updatedAddress->getCustomFieldsValue('randomCustomField'));
    }

    private function createMember(string $email): string
    {
        $memberId = Uuid::randomHex();

        static::getContainer()->get('member.repository')->create([[
            'id' => $memberId,
            'channelId' => $this->ids->get('channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'groupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max Member',
            'memberNumber' => $memberId,
            'active' => true,
        ]], Context::createDefaultContext());

        return $memberId;
    }

    private function getRegionId(string $countryId): string
    {
        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('countryId', $countryId))
            ->setLimit(1);

        $regionId = static::getContainer()->get('region.repository')
            ->searchIds($criteria, Context::createDefaultContext())
            ->firstId();
        static::assertNotNull($regionId);

        return $regionId;
    }
}
