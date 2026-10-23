<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\CheckoutPermissions;
use Contena\Core\Content\MeasurementSystem\MeasurementUnits;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(ChannelContext::class)]
class ChannelContextTest extends TestCase
{
    protected function tearDown(): void
    {
        // reset field visibility, see `testGetTokenIsNotAccessibleFromTwigRenderingContext()` test
        FieldVisibility::$isInTwigRenderingContext = false;
    }

    public function testGetRuleIdsByAreas(): void
    {
        $channelContext = Generator::generateChannelContext();

        $idA = Uuid::randomHex();
        $idB = Uuid::randomHex();
        $idC = Uuid::randomHex();
        $idD = Uuid::randomHex();

        $areaRuleIds = [
            'a' => [$idA, $idB],
            'b' => [$idA, $idC, $idD],
            'c' => [$idB],
            'd' => [$idC],
        ];

        $channelContext->setAreaRuleIds($areaRuleIds);

        static::assertSame($areaRuleIds, $channelContext->getAreaRuleIds());

        static::assertSame([$idA, $idB], $channelContext->getRuleIdsByAreas(['a']));
        static::assertSame([$idA, $idB, $idC, $idD], $channelContext->getRuleIdsByAreas(['a', 'b']));
        static::assertSame([$idA, $idB], $channelContext->getRuleIdsByAreas(['a', 'c']));
        static::assertSame([$idC], $channelContext->getRuleIdsByAreas(['d']));
        static::assertSame([], $channelContext->getRuleIdsByAreas(['f']));
        static::assertSame([
            'extensions' => [],
            'system' => 'metric',
            'units' => [
                'length' => 'mm',
                'weight' => 'kg',
            ],
        ], $channelContext->getMeasurementSystem()->jsonSerialize());

        $newMeasurementSystem = new MeasurementUnits(
            'imperial',
            [
                'length' => 'in',
                'weight' => 'lb',
            ]
        );

        $channelContext->setMeasurementSystem($newMeasurementSystem);
        static::assertSame([
            'extensions' => [],
            'system' => 'imperial',
            'units' => [
                'length' => 'in',
                'weight' => 'lb',
            ],
        ], $channelContext->getMeasurementSystem()->jsonSerialize());
    }

    public function testGetRuleIdsByAreasDeduplicatesWithinAndAcrossAreas(): void
    {
        $channelContext = Generator::generateChannelContext();

        $idA = Uuid::randomHex();
        $idB = Uuid::randomHex();
        $idC = Uuid::randomHex();

        $channelContext->setAreaRuleIds([
            // duplicate id within a single area must be collapsed
            'a' => [$idA, $idB, $idA],
            'b' => [$idB, $idC],
        ]);

        // within-area dedup, insertion order preserved
        static::assertSame([$idA, $idB], $channelContext->getRuleIdsByAreas(['a']));
        // cross-area dedup ($idB appears in both areas), first-occurrence order preserved
        static::assertSame([$idA, $idB, $idC], $channelContext->getRuleIdsByAreas(['a', 'b']));
        // the result is a sequentially indexed list (no gaps from dedup)
        static::assertSame([0, 1, 2], array_keys($channelContext->getRuleIdsByAreas(['a', 'b'])));
    }

    public function testWithPermissions(): void
    {
        $channelContext = Generator::generateChannelContext();
        $permissionsBefore = $channelContext->getPermissions();
        static::assertSame([], $permissionsBefore);

        $called = false;
        $channelContext->withPermissions(
            [CheckoutPermissions::PERSIST_CART_ERRORS => true],
            static function (ChannelContext $context) use (&$called): void {
                $called = true;

                static::assertTrue($context->hasPermission(CheckoutPermissions::PERSIST_CART_ERRORS));
            },
        );

        static::assertTrue($called);
        $permissionsAfter = $channelContext->getPermissions();
        static::assertSame([], $permissionsAfter);
    }

    public function testWithPermissionsWithLockedPermissions(): void
    {
        $channelContext = Generator::generateChannelContext();
        $channelContext->lockPermissions();
        $permissionsBefore = $channelContext->getPermissions();
        static::assertSame([], $permissionsBefore);

        $called = false;
        $channelContext->withPermissions(
            [CheckoutPermissions::PERSIST_CART_ERRORS => true],
            static function (ChannelContext $context) use (&$called): void {
                $called = true;

                static::assertCount(0, $context->getPermissions());
            },
        );

        static::assertTrue($called);
        $permissionsAfter = $channelContext->getPermissions();
        static::assertSame([], $permissionsAfter);
    }

    public function testChannelContextStateFunctionPassesResetsAndKeepsState(): void
    {
        $manualState = 'manual-state';
        $closureState = 'closure-state';

        $channelContext = Generator::generateChannelContext();
        $channelContext->addState($manualState);

        static::assertTrue($channelContext->hasState($manualState));
        static::assertTrue($channelContext->getContext()->hasState($manualState));
        static::assertFalse($channelContext->hasState($closureState));
        static::assertFalse($channelContext->getContext()->hasState($closureState));

        $closureStates = $channelContext->state(static function (ChannelContext $closureContext): array {
            return $closureContext->getStates();
        }, $closureState);

        static::assertContains($closureState, $closureStates);
        static::assertContains($manualState, $closureStates);
        static::assertTrue($channelContext->hasState($manualState));
        static::assertTrue($channelContext->getContext()->hasState($manualState));
        static::assertFalse($channelContext->hasState($closureState));
        static::assertFalse($channelContext->getContext()->hasState($closureState));
    }

    public function testGetTokenIsNotAccessibleFromTwigRenderingContext(): void
    {
        $channelContext = Generator::generateChannelContext();
        // outside of twig rendering context, token is accessible
        $channelContext->getToken();

        // fake twig rendering context, see `\Contena\Core\Framework\Adapter\Twig\SwTwigFunction::getAttribute()`
        FieldVisibility::$isInTwigRenderingContext = true;

        $this->expectExceptionObject(ChannelException::contextTokenNotAccessible());
        $channelContext->getToken();
    }
}
