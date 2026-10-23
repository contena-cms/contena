<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Cookie\Hook;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cookie\Service\CookieProvider;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Test\AppSystemTestBehaviour;

/**
 * @internal
 */
class CookieGroupCollectHookTest extends TestCase
{
    use AppSystemTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testAppScriptCanManipulateCookieGroups(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/SwagCookieScript');

        $cookieGroups = static::getContainer()->get(CookieProvider::class)
            ->getCookieGroups(new Request(), Generator::generateChannelContext());

        static::assertNull($cookieGroups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL));
        static::assertNotNull($cookieGroups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED));

        $appGroup = $cookieGroups->get('swag.cookies.group');
        static::assertNotNull($appGroup);

        $entries = $appGroup->getEntries();
        static::assertNotNull($entries);
        static::assertCount(1, $entries);
        static::assertNotNull($entries->get('swag-app-keep'));
        static::assertNull($entries->get('swag-app-conditional'));
    }
}
