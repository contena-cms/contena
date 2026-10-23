<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Page\Account;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserException;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Page\Account\UserGroupRegistration\UserGroupRegistrationPageLoader;
use Contena\Frontend\Test\Page\FrontendPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class UserGroupRegistrationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendPageTestBehaviour;

    private IdsCollection $ids;

    private ChannelContext $channel;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->channel = $this->createChannelContext();
    }

    public function test404(): void
    {
        static::expectException(UserException::class);
        $request = new Request();
        $request->attributes->set('userGroupId', Defaults::LANGUAGE_SYSTEM);

        $this->getPageLoader()->load($request, $this->channel);
    }

    public function testGetConfiguration(): void
    {
        $userGroupRepository = static::getContainer()->get('user_group.repository');
        $userGroupRepository->create([
            [
                'id' => $this->ids->create('group'),
                'name' => 'foo',
                'registrationActive' => true,
                'registrationTitle' => 'test',
                'registrationChannels' => [['id' => $this->channel->getChannelId()]],
            ],
        ], Context::createDefaultContext());

        $request = new Request();
        $request->attributes->set('userGroupId', $this->ids->get('group'));

        $page = $this->getPageLoader()->load($request, $this->channel);
        static::assertSame($this->ids->get('group'), $page->getGroup()->getId());
        static::assertSame('test', $page->getGroup()->getRegistrationTitle());
    }

    protected function getPageLoader(): UserGroupRegistrationPageLoader
    {
        return static::getContainer()->get(UserGroupRegistrationPageLoader::class);
    }
}
