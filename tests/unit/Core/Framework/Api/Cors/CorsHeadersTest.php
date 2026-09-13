<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Cors;

use Contena\Core\Framework\Api\Cors\CorsHeaders;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CorsHeaders::class)]
class CorsHeadersTest extends TestCase
{
    public function testEmptyByDefault(): void
    {
        $headers = new CorsHeaders();

        static::assertSame([], $headers->getAllowed());
        static::assertSame([], $headers->getExposed());
    }

    public function testTheTwoListsAreIndependent(): void
    {
        $headers = new CorsHeaders();
        $headers->addAllowed('ct-plan');
        $headers->addExposed('ct-state');

        static::assertSame(['ct-plan'], $headers->getAllowed());
        static::assertSame(['ct-state'], $headers->getExposed());
    }

    public function testHeadersKeepTheOrderTheyWereAddedIn(): void
    {
        $headers = new CorsHeaders();
        $headers->addAllowed('ct-first', 'ct-second');
        $headers->addAllowed('ct-third');

        static::assertSame(['ct-first', 'ct-second', 'ct-third'], $headers->getAllowed());
    }

    public function testDuplicatesKeepTheFirstSpelling(): void
    {
        $headers = new CorsHeaders();
        $headers->addAllowed('CT-Plan');
        $headers->addAllowed('ct-plan');
        $headers->addExposed('ct-state', 'CT-STATE');

        static::assertSame(['CT-Plan'], $headers->getAllowed());
        static::assertSame(['ct-state'], $headers->getExposed());
    }

    public function testRemovingMatchesAnySpelling(): void
    {
        $headers = new CorsHeaders();
        $headers->addAllowed('ct-plan', 'ct-interval');
        $headers->addExposed('ct-state');

        $headers->removeAllowed('CT-PLAN');
        $headers->removeExposed('Ct-State');

        static::assertSame(['ct-interval'], $headers->getAllowed());
        static::assertSame([], $headers->getExposed());
    }

    public function testRemovingAHeaderThatWasNeverAddedIsANoop(): void
    {
        $headers = new CorsHeaders();
        $headers->addAllowed('ct-plan');

        $headers->removeAllowed('ct-unknown');

        static::assertSame(['ct-plan'], $headers->getAllowed());
    }

    public function testRemovingFromOneListLeavesTheOtherAlone(): void
    {
        $headers = new CorsHeaders();
        $headers->addAllowed('ct-plan');
        $headers->addExposed('ct-plan');

        $headers->removeAllowed('ct-plan');

        static::assertSame([], $headers->getAllowed());
        static::assertSame(['ct-plan'], $headers->getExposed());
    }

    public function testAHeaderCanBeAddedAgainAfterItWasRemoved(): void
    {
        $headers = new CorsHeaders();
        $headers->addAllowed('ct-plan');
        $headers->removeAllowed('ct-plan');
        $headers->addAllowed('CT-Plan');

        static::assertSame(['CT-Plan'], $headers->getAllowed());
    }
}
