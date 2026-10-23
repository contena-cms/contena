<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Page\Account;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoadedEvent;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoader;
use Contena\Frontend\Test\Page\FrontendPageTestBehaviour;
use Contena\Tests\Integration\Frontend\Page\FrontendPageTestConstants;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class LoginPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendPageTestBehaviour;

    public function testItLoadsWithAUser(): void
    {
        $request = new Request();
        $context = $this->createChannelContextWithLoggedInUserAndWithNavigation();

        $event = null;
        $this->catchEvent(AccountLoginPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertCount(FrontendPageTestConstants::COUNTRY_COUNT, $page->getCountries());
        static::assertInstanceOf(CountryStateCollection::class, $page->getCountries()->first()?->getStates());
        self::assertPageEvent(AccountLoginPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testItLoadsWithoutAUser(): void
    {
        $request = new Request();
        $context = $this->createChannelContextWithNavigation();
        $page = $this->getPageLoader()->load($request, $context);

        static::assertCount(FrontendPageTestConstants::COUNTRY_COUNT, $page->getCountries());
        static::assertInstanceOf(CountryStateCollection::class, $page->getCountries()->first()?->getStates());
    }

    protected function getPageLoader(): AccountLoginPageLoader
    {
        return static::getContainer()->get(AccountLoginPageLoader::class);
    }
}
