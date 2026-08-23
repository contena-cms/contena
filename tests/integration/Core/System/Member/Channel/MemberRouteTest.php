<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Routing\RoutingException;
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
class MemberRouteTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('channel'),
        ]);
    }

    public function testNotLoggedIn(): void
    {
        $this->browser
            ->request(
                'GET',
                '/channel-api/account/member',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame(RoutingException::CHANNEL_MEMBER_NOT_LOGGED_IN, $response['errors'][0]['code']);
    }

    public function testValid(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $id = $this->createMember($email);

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

        $this->browser
            ->request(
                'GET',
                '/channel-api/account/member',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame($id, $response['id']);
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
