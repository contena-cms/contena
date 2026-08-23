<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
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
class DeleteAddressRouteTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->ids->set('country', $this->getCountryIdByIsoCode('CN'));

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('channel'),
            'countryId' => $this->ids->get('country'),
            'countries' => [['id' => $this->ids->get('country')]],
        ]);

        $email = Uuid::randomHex() . '@example.com';
        $this->createMember($email);

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

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }

    public function testDeleteNewCreatedAddress(): void
    {
        // Create
        $data = [
            'firstName' => 'Test',
            'lastName' => 'Member',
            'street' => 'Example Street 1',
            'city' => 'Beijing',
            'zipcode' => '100000',
            'countryId' => $this->ids->get('country'),
        ];

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/address',
                $data
            );

        $addressId = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['id'];

        // Check is listed
        $this->browser
            ->request(
                'POST',
                '/channel-api/account/list-address',
                [
                ]
            );

        static::assertSame(1, json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['total']);

        // Delete
        $this->browser
            ->request(
                'DELETE',
                '/channel-api/account/address/' . $addressId
            );

        static::assertSame(204, $this->browser->getResponse()->getStatusCode());

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/list-address',
                [
                ]
            );

        static::assertSame(0, json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['total']);
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
}
