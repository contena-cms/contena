<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\LandingPage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Content\LandingPage\LandingPageEntity;
use Contena\Frontend\Page\LandingPage\LandingPage;

/**
 * @internal
 */
#[CoversClass(LandingPage::class)]
class LandingPageTest extends TestCase
{
    public function testLandingPage(): void
    {
        $page = new LandingPage();
        $entity = new LandingPageEntity();
        $navigationId = 'navigation-id';

        $page->setLandingPage($entity);
        $page->setNavigationId($navigationId);

        static::assertSame(LandingPageDefinition::ENTITY_NAME, $page->getEntityName());
        static::assertSame($entity, $page->getLandingPage());
        static::assertSame($navigationId, $page->getNavigationId());
    }
}
