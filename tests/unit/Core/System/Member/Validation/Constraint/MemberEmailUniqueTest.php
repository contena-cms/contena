<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Validation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Member\Validation\Constraint\MemberEmailUnique;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(MemberEmailUnique::class)]
class MemberEmailUniqueTest extends TestCase
{
    public function testConstructWithChannelContext(): void
    {
        $channelContext = Generator::generateChannelContext();

        $constraint = new MemberEmailUnique(channelContext: $channelContext);

        static::assertSame($channelContext, $constraint->getChannelContext());
        static::assertSame('The email address {{ email }} is already in use.', $constraint->getMessage());
    }

    public function testConstructWithCustomMessage(): void
    {
        $constraint = new MemberEmailUnique(
            channelContext: Generator::generateChannelContext(),
            message: 'Custom message for {{ email }}.'
        );

        static::assertSame('Custom message for {{ email }}.', $constraint->getMessage());
    }
}
