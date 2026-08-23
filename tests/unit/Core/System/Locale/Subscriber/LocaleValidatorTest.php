<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Locale\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Locale\LocaleDefinition;
use Contena\Core\System\Locale\Subscriber\LocaleValidator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(LocaleValidator::class)]
class LocaleValidatorTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $definitionRegistry;

    protected function setUp(): void
    {
        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [LocaleDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }

    public function testItValidates(): void
    {
        $validator = new LocaleValidator();

        $localeDefinition = $this->definitionRegistry->get(LocaleDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new DeleteCommand(
                    $localeDefinition,
                    [
                        'id' => Uuid::randomBytes(),
                    ],
                    static::createStub(EntityExistence::class)
                ),
                new InsertCommand(
                    $localeDefinition,
                    [
                        'name' => 'foobar',
                    ],
                    ['id' => Uuid::randomBytes()],
                    static::createStub(EntityExistence::class),
                    '/0/'
                ),
                new InsertCommand(
                    $localeDefinition,
                    [
                        'code' => 'de-DE',
                    ],
                    ['id' => Uuid::randomBytes()],
                    static::createStub(EntityExistence::class),
                    '/0/'
                ),
                new InsertCommand(
                    $localeDefinition,
                    [
                        'code' => 'foo-BAR',
                    ],
                    ['id' => Uuid::randomBytes()],
                    static::createStub(EntityExistence::class),
                    '/0/'
                ),
            ]
        );

        $validator->preWriteValidateEvent($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
    }
}
