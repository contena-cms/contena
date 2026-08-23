<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Page\Account;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\Member\MemberEntity;
use Contena\Frontend\Page\Account\Profile\AccountProfilePageLoadedEvent;
use Contena\Frontend\Page\Account\Profile\AccountProfilePageLoader;
use Contena\Frontend\Test\Page\FrontendPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ProfilePageTest extends TestCase
{
    use FrontendPageTestBehaviour;
    use IntegrationTestBehaviour;

    public function testItLoadsTheProfilePage(): void
    {
        $request = new Request();
        $context = $this->createChannelContext();
        $context->assign(['member' => new MemberEntity()]);

        $event = null;
        $this->catchEvent(AccountProfilePageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        self::assertPageEvent(AccountProfilePageLoadedEvent::class, $event, $context, $request, $page);
    }

    protected function getPageLoader(): AccountProfilePageLoader
    {
        return static::getContainer()->get(AccountProfilePageLoader::class);
    }
}
