<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Member\Validation\PasswordValidationFactory;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[CoversClass(PasswordValidationFactory::class)]
class PasswordValidationFactoryTest extends TestCase
{
    private StaticSystemConfigService $systemConfigService;

    private PasswordValidationFactory $factory;

    protected function setUp(): void
    {
        $this->systemConfigService = new StaticSystemConfigService();
        $this->factory = new PasswordValidationFactory($this->systemConfigService);
    }

    public function testCreateValidation(): void
    {
        $channelContext = Generator::generateChannelContext();
        $this->systemConfigService->set('core.loginRegistration.passwordMinLength', 10, $channelContext->getChannelId());

        $definition = $this->factory->create($channelContext);

        static::assertSame('password.create', $definition->getName());
        $constraints = $definition->getProperties()['password'];
        static::assertCount(2, $constraints);
        static::assertContainsEquals(new NotBlank(), $constraints);
        static::assertContainsEquals(
            new Length(min: 10, max: 4096, minMessage: 'VIOLATION::PASSWORD_IS_TOO_SHORT', maxMessage: 'VIOLATION::PASSWORD_IS_TOO_LONG'),
            $constraints
        );
    }

    public function testUpdateValidation(): void
    {
        $channelContext = Generator::generateChannelContext();
        $this->systemConfigService->set('core.loginRegistration.passwordMinLength', 10, $channelContext->getChannelId());

        $definition = $this->factory->update($channelContext);

        static::assertSame('password.update', $definition->getName());
        $constraints = $definition->getProperties()['password'];
        static::assertCount(2, $constraints);
        static::assertContainsEquals(new NotBlank(), $constraints);
        static::assertContainsEquals(
            new Length(min: 10, max: 4096, minMessage: 'VIOLATION::PASSWORD_IS_TOO_SHORT', maxMessage: 'VIOLATION::PASSWORD_IS_TOO_LONG'),
            $constraints
        );
    }
}
