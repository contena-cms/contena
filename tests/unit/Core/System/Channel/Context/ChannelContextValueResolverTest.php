<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextValueResolver;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
#[CoversClass(ChannelContextValueResolver::class)]
class ChannelContextValueResolverTest extends TestCase
{
    public function testResolvesChannelContextArgument(): void
    {
        $context = Generator::generateChannelContext(token: 'token');
        $request = new Request(attributes: [PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => $context]);

        $resolved = iterator_to_array(new ChannelContextValueResolver()->resolve(
            $request,
            new ArgumentMetadata('context', ChannelContext::class, false, false, null),
        ));

        static::assertSame([$context], $resolved);
    }

    public function testIgnoresOtherArguments(): void
    {
        $resolved = iterator_to_array(new ChannelContextValueResolver()->resolve(
            new Request(),
            new ArgumentMetadata('value', 'string', false, false, null),
        ));

        static::assertSame([], $resolved);
    }
}
