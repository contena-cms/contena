<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Page\Account;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Member\MemberException;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Page\Account\MemberGroupRegistration\MemberGroupRegistrationPageLoader;
use Contena\Frontend\Test\Page\FrontendPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class MemberGroupRegistrationTest extends TestCase
{
    use FrontendPageTestBehaviour;
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function test404(): void
    {
        $this->expectException(MemberException::class);

        $request = new Request();
        $request->attributes->set('memberGroupId', Uuid::randomHex());

        $this->getPageLoader()->load($request, $this->createChannelContext());
    }

    public function testGetConfiguration(): void
    {
        $channelContext = $this->createChannelContext();
        $memberGroupRepository = static::getContainer()->get('member_group.repository');
        $memberGroupRepository->create([[
            'id' => $this->ids->create('group'),
            'name' => 'foo',
            'registrationActive' => true,
            'registrationTitle' => 'test',
            'registrationChannels' => [['id' => $channelContext->getChannelId()]],
        ]], Context::createDefaultContext());

        $request = new Request();
        $request->attributes->set('memberGroupId', $this->ids->get('group'));

        $page = $this->getPageLoader()->load($request, $channelContext);

        static::assertSame($this->ids->get('group'), $page->getGroup()->getId());
        static::assertSame('test', $page->getGroup()->getRegistrationTitle());
    }

    protected function getPageLoader(): MemberGroupRegistrationPageLoader
    {
        return static::getContainer()->get(MemberGroupRegistrationPageLoader::class);
    }
}
