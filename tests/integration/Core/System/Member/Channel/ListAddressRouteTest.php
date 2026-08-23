<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('channel-api')]
class ListAddressRouteTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

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

        $email = Uuid::randomHex() . '@example.com';
        $memberId = $this->createMember($email);
        $this->createAddress($memberId);

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/login',
                [
                    'email' => $email,
                    'password' => 'contenaAdmin',
                ]
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }

    public function testListAddresses(): void
    {
        $this->browser
            ->request(
                'POST',
                '/channel-api/account/list-address',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $response['total']);
        static::assertNotEmpty($response['elements']);
        static::assertSame('Max', $response['elements'][0]['firstName']);
        static::assertSame('Member', $response['elements'][0]['lastName']);
        static::assertSame('Example Street 1', $response['elements'][0]['street']);
        static::assertSame('Beijing', $response['elements'][0]['city']);
        static::assertSame('100000', $response['elements'][0]['zipcode']);
        static::assertSame($this->ids->get('country'), $response['elements'][0]['countryId']);
        static::assertSame($this->ids->get('region'), $response['elements'][0]['regionId']);
        static::assertSame($this->ids->get('country'), $response['elements'][0]['country']['id']);
        static::assertSame($this->ids->get('region'), $response['elements'][0]['region']['id']);
    }

    public function testListAddressesIncludes(): void
    {
        $this->browser
            ->request(
                'POST',
                '/channel-api/account/list-address',
                [
                    'includes' => [
                        'member_address' => [
                            'firstName',
                        ],
                    ],
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $response['total']);
        static::assertNotEmpty($response['elements']);
        static::assertSame([
            'firstName' => 'Max',
            'apiAlias' => 'member_address',
        ], $response['elements'][0]);
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

    private function createAddress(string $memberId): void
    {
        static::getContainer()->get('member_address.repository')->create([[
            'id' => $this->ids->create('address'),
            'memberId' => $memberId,
            'countryId' => $this->ids->get('country'),
            'regionId' => $this->ids->get('region'),
            'firstName' => 'Max',
            'lastName' => 'Member',
            'street' => 'Example Street 1',
            'city' => 'Beijing',
            'zipcode' => '100000',
        ]], Context::createDefaultContext());
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
