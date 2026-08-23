<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\Member\MemberException;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('channel-api')]
class MemberGroupRegistrationSettingsRouteTest extends TestCase
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

    public function testInvalidId(): void
    {
        $this->browser
            ->request(
                'GET',
                '/channel-api/member-group-registration/config/' . Defaults::LANGUAGE_SYSTEM
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(404, $this->browser->getResponse()->getStatusCode());

        static::assertArrayHasKey('errors', $response);
        static::assertSame(MemberException::MEMBER_GROUP_REGISTRATION_NOT_FOUND, $response['errors'][0]['code']);
    }

    public function testWithValidConfig(): void
    {
        $memberGroupRepository = static::getContainer()->get('member_group.repository');
        $memberGroupRepository->create([
            [
                'id' => $this->ids->create('group'),
                'name' => 'foo',
                'registrationActive' => true,
                'registrationTitle' => 'test',
                'registrationChannels' => [['id' => $this->getChannelApiChannelId()]],
            ],
        ], Context::createDefaultContext());

        $this->browser
            ->request(
                'GET',
                '/channel-api/member-group-registration/config/' . $this->ids->get('group')
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        static::assertSame($this->ids->get('group'), $response['id']);
        static::assertSame('test', $response['registrationTitle']);
    }
}
