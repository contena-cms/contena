<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Generator;
use Contena\Frontend\Page\Cms\CmsPageLoadedHook;

/**
 * @internal
 */
#[CoversClass(CmsPageLoadedHook::class)]
class CmsPageLoadedHookTest extends TestCase
{
    public function testCmsPageLoadedHook(): void
    {
        $page = new CmsPageEntity();
        $hook = new CmsPageLoadedHook($page, Generator::generateChannelContext());
        static::assertSame('cms-page-loaded', $hook->getName());
        static::assertSame($page, $hook->getPage());
    }
}
