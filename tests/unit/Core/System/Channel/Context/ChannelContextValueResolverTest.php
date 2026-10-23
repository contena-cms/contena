<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Context\ChannelContextValueResolver;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
#[CoversClass(ChannelContextValueResolver::class)]
class ChannelContextValueResolverTest extends TestCase
{
    public function testIgnoresArgumentsOfOtherTypes(): void
    {
        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => Generator::generateChannelContext(),
        ]);

        $resolved = (new ChannelContextValueResolver())->resolve($request, self::argument(Context::class));

        static::assertSame([], iterator_to_array($resolved));
    }

    /**
     * @param array<string, ChannelContext> $attributes
     */
    #[DataProvider('channelContextProvider')]
    public function testResolvesTheChannelContext(array $attributes, ChannelContext $expected): void
    {
        $resolved = (new ChannelContextValueResolver())->resolve(
            new Request(attributes: $attributes),
            self::argument(ChannelContext::class),
        );

        static::assertSame([$expected], iterator_to_array($resolved));
    }

    public static function channelContextProvider(): \Generator
    {
        $sessionContext = Generator::generateChannelContext();
        $orderContext = Generator::generateChannelContext(token: 'order-token');

        yield 'order-based context of an opted-in route wins over the session context' => [
            [
                PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => $sessionContext,
                PlatformRequest::ATTRIBUTE_EFFECTIVE_CHANNEL_CONTEXT_OBJECT => $orderContext,
            ],
            $orderContext,
        ];

        yield 'session context is used without an order-based context' => [
            [PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => $sessionContext],
            $sessionContext,
        ];
    }

    private static function argument(string $type): ArgumentMetadata
    {
        return new ArgumentMetadata('context', $type, isVariadic: false, hasDefaultValue: false, defaultValue: null);
    }
}
