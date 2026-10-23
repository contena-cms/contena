<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('store-api')]
class UserGroupRegistrationSettingsRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->assignChannelContext($this->browser);
    }

    public function testInvalidId(): void
    {
        $this->browser
            ->request(
                'GET',
                '/store-api/user-group-registration/config/' . Defaults::LANGUAGE_SYSTEM
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(404, $this->browser->getResponse()->getStatusCode());

        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__CUSTOMER_GROUP_REGISTRATION_NOT_FOUND', $response['errors'][0]['code']);
    }

    public function testWithValidConfig(): void
    {
        $userGroupRepository = static::getContainer()->get('user_group.repository');
        $userGroupRepository->create([
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
                '/store-api/user-group-registration/config/' . $this->ids->get('group')
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        static::assertSame($this->ids->get('group'), $response['id']);
        static::assertSame('test', $response['registrationTitle']);
        static::assertArrayHasKey('registrationOnlyCompanyRegistration', $response);
        static::assertNull($response['registrationOnlyCompanyRegistration']);
    }

    public function testWithCompanyOnlyRegistrationEnabled(): void
    {
        $userGroupRepository = static::getContainer()->get('user_group.repository');
        $userGroupRepository->create([
            [
                'id' => $this->ids->create('company-group'),
                'name' => 'foo',
                'registrationActive' => true,
                'registrationTitle' => 'test',
                'registrationOnlyCompanyRegistration' => true,
                'registrationChannels' => [['id' => $this->getChannelApiChannelId()]],
            ],
        ], Context::createDefaultContext());

        $this->browser
            ->request(
                'GET',
                '/store-api/user-group-registration/config/' . $this->ids->get('company-group')
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        static::assertArrayHasKey('registrationOnlyCompanyRegistration', $response);
        static::assertTrue($response['registrationOnlyCompanyRegistration']);
    }
}
