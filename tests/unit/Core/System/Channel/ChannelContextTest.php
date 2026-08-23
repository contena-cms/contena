<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
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
        FieldVisibility::$isInTwigRenderingContext = false;
    }

    public function testRuleAreasAndPermissionsUseTheContextContracts(): void
    {
        $context = Generator::generateChannelContext(token: 'token');

        $areaRuleIds = ['content' => ['rule-a', 'rule-b'], 'member' => ['rule-b', 'rule-c']];
        $context->setAreaRuleIds($areaRuleIds);

        static::assertSame(['rule-a', 'rule-b', 'rule-c'], $context->getRuleIdsByAreas(['content', 'member']));
        static::assertSame([], $context->getPermissions());

        $context->withPermissions(['channel:read' => true], static function (ChannelContext $context): void {
            static::assertTrue($context->hasPermission('channel:read'));
        });

        static::assertSame([], $context->getPermissions());
    }

    public function testTokenCannotBeReadWhileRenderingTwig(): void
    {
        $context = Generator::generateChannelContext(token: 'token');

        FieldVisibility::$isInTwigRenderingContext = true;

        $this->expectExceptionObject(ChannelException::contextTokenNotAccessible());
        $context->getToken();
    }

    public function testChannelContextStateFunctionPassesResetsAndKeepsState(): void
    {
        $manualState = 'manual-state';
        $closureState = 'closure-state';
        $channelContext = Generator::generateChannelContext(token: 'token');
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
}
