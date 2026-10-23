<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Validation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Validation\Constraint\UserEmailUnique;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Feature\FeatureException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * @internal
 */
#[CoversClass(UserEmailUnique::class)]
class UserEmailUniqueTest extends TestCase
{
    public function testConstructWithChannelContext(): void
    {
        $channelContext = $this->createChannelContext();

        $constraint = new UserEmailUnique(channelContext: $channelContext);

        static::assertSame($channelContext, $constraint->getChannelContext());
        static::assertSame('The email address {{ email }} is already in use.', $constraint->getMessage());
    }

    public function testConstructWithCustomMessage(): void
    {
        $constraint = new UserEmailUnique(
            channelContext: $this->createChannelContext(),
            message: 'Custom message for {{ email }}.'
        );

        static::assertSame('Custom message for {{ email }}.', $constraint->getMessage());
    }

    public function testConstructWithOptionsThrows(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: ' . Feature::deprecatedMethodMessage(
                UserEmailUnique::class,
                '__construct',
                'v6.8.0.0',
                'Use $channelContext argument instead of providing it in $options array'
            )
        ));

        new UserEmailUnique(['channelContext' => $this->createChannelContext()]);
    }

    public function testGetContextThrows(): void
    {
        $constraint = new UserEmailUnique(channelContext: $this->createChannelContext());

        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: ' . Feature::deprecatedMethodMessage(
                UserEmailUnique::class,
                'getContext',
                'v6.8.0.0',
                'getChannelContext->getContext()'
            )
        ));

        $constraint->getContext();
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConstructWithoutChannelContextThrows(): void
    {
        $this->expectExceptionObject(new MissingOptionsException(
            'Option "channelContext" must be given for constraint ' . UserEmailUnique::class,
            ['context']
        ));

        new UserEmailUnique(channelContext: null);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    #[IgnoreDeprecations]
    public function testConstructWithOptionsArray(): void
    {
        $channelContext = $this->createChannelContext();

        $constraint = new UserEmailUnique(['channelContext' => $channelContext]);

        static::assertSame($channelContext, $constraint->getChannelContext());
        static::assertSame($channelContext->getContext(), $constraint->getContext());
        static::assertSame('The email address {{ email }} is already in use.', $constraint->getMessage());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConstructWithOptionsArrayWithoutChannelContextThrows(): void
    {
        $this->expectExceptionObject(new MissingOptionsException(
            'Option "channelContext" must be given for constraint ' . UserEmailUnique::class,
            ['context']
        ));

        new UserEmailUnique([]);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConstructWithOptionsArrayWithInvalidContextThrows(): void
    {
        $this->expectExceptionObject(new MissingOptionsException(
            'Option "context" must be given for constraint ' . UserEmailUnique::class,
            ['context']
        ));

        /** @phpstan-ignore argument.type (intentionally wrong option type for test purpose) */
        new UserEmailUnique([
            'channelContext' => $this->createChannelContext(),
            'context' => 'not-a-context',
        ]);
    }

    private function createChannelContext(): ChannelContext
    {
        $context = Context::createDefaultContext();

        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getContext')->willReturn($context);

        return $channelContext;
    }
}
