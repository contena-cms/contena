<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\SalesChannel\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeDefinition;
use Contena\Core\System\SalesChannel\Exception\DefaultSalesChannelTypeCannotBeDeleted;
use Contena\Core\System\SalesChannel\Subscriber\SalesChannelTypeValidator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelTypeValidator::class)]
class SalesChannelTypeValidatorTest extends TestCase
{
    public function testOnlyProtectedSalesChannelTypeDeletionIsRejected(): void
    {
        $definitionRegistry = new StaticDefinitionInstanceRegistry(
            [SalesChannelTypeDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );
        $definition = $definitionRegistry->get(SalesChannelTypeDefinition::class);
        $existence = EntityExistence::createEmpty();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new DeleteCommand($definition, ['id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT)], $existence),
                new DeleteCommand($definition, ['id' => Uuid::randomBytes()], $existence),
                new InsertCommand($definition, [], ['id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_API)], $existence, '/sales-channel-type'),
            ],
        );

        (new SalesChannelTypeValidator())->preWriteValidateEvent($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        static::assertInstanceOf(DefaultSalesChannelTypeCannotBeDeleted::class, $event->getExceptions()->getExceptions()[0]);
    }
}
