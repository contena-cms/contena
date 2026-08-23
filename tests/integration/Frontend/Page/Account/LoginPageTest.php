<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Page\Account;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\Member\MemberEntity;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoadedEvent;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoader;
use Contena\Frontend\Test\Page\FrontendPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class LoginPageTest extends TestCase
{
    use FrontendPageTestBehaviour;
    use IntegrationTestBehaviour;

    public function testItLoadsWithAMember(): void
    {
        $request = new Request();
        $context = $this->createChannelContext();
        $context->assign(['member' => new MemberEntity()]);

        $event = null;
        $this->catchEvent(AccountLoginPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertNotEmpty($page->getCountries());
        self::assertPageEvent(AccountLoginPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testItLoadsWithoutAMember(): void
    {
        $request = new Request();
        $context = $this->createChannelContext();
        $page = $this->getPageLoader()->load($request, $context);

        static::assertNotEmpty($page->getCountries());
    }

    protected function getPageLoader(): AccountLoginPageLoader
    {
        return static::getContainer()->get(AccountLoginPageLoader::class);
    }
}
