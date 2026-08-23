<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Subscriber;

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
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelType\ChannelTypeDefinition;
use Contena\Core\System\Channel\Exception\DefaultChannelTypeCannotBeDeleted;
use Contena\Core\System\Channel\Subscriber\ChannelTypeValidator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ChannelTypeValidator::class)]
class ChannelTypeValidatorTest extends TestCase
{
    public function testOnlyProtectedChannelTypeDeletionIsRejected(): void
    {
        $definitionRegistry = new StaticDefinitionInstanceRegistry(
            [ChannelTypeDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );
        $definition = $definitionRegistry->get(ChannelTypeDefinition::class);
        $existence = EntityExistence::createEmpty();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new DeleteCommand($definition, ['id' => Uuid::fromHexToBytes(Defaults::CHANNEL_TYPE_WEB)], $existence),
                new DeleteCommand($definition, ['id' => Uuid::randomBytes()], $existence),
                new InsertCommand($definition, [], ['id' => Uuid::fromHexToBytes(Defaults::CHANNEL_TYPE_API)], $existence, '/channel-type'),
            ],
        );

        new ChannelTypeValidator()->preWriteValidateEvent($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        static::assertInstanceOf(DefaultChannelTypeCannotBeDeleted::class, $event->getExceptions()->getExceptions()[0]);
    }
}
