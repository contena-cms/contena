<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Currency;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\Currency\CurrencyDefinition;
use Contena\Core\System\Currency\CurrencyValidator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(CurrencyValidator::class)]
class CurrencyValidatorTest extends TestCase
{
    public function testOnlyDefaultCurrencyDeletionIsRejected(): void
    {
        $definitionRegistry = new StaticDefinitionInstanceRegistry(
            [CurrencyDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );
        $definition = $definitionRegistry->get(CurrencyDefinition::class);
        $existence = EntityExistence::createEmpty();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new DeleteCommand($definition, ['id' => Uuid::randomBytes()], $existence),
            ],
        );

        (new CurrencyValidator())->preValidate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame(CurrencyValidator::VIOLATION_DELETE_DEFAULT_CURRENCY, $exception->getViolations()->get(0)->getCode());
    }
}
