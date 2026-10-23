<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Write\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(WriteCommand::class)]
class WriteCommandTest extends TestCase
{
    public function testDetectsAnyPayloadFieldIncludingNull(): void
    {
        $command = $this->createCommand(['id' => Uuid::randomBytes(), 'active' => null]);

        static::assertTrue($command->hasAnyField('name', 'active'));
        static::assertFalse($command->hasAnyField('name', 'stock'));
        static::assertFalse($command->hasAnyField());
    }

    public function testExposesAndMutatesCommandState(): void
    {
        $id = Uuid::randomBytes();
        $existence = EntityExistence::createEmpty();
        $command = $this->createCommand(['id' => $id], $existence);

        static::assertSame('blog', $command->getEntityName());
        static::assertSame(['id' => $id], $command->getPrimaryKey());
        static::assertSame(['id' => Uuid::fromBytesToHex($id)], $command->getDecodedPrimaryKey());
        static::assertSame($existence, $command->getEntityExistence());
        static::assertSame('/blog', $command->getPath());
        static::assertSame(['id' => $id], $command->getPayload());
        static::assertTrue($command->isValid());
        static::assertTrue($command->hasField('id'));
        static::assertFalse($command->hasField('active'));
        static::assertFalse($command->isFailed());

        $command->addPayload('active', false);
        $command->setFailed(true);

        static::assertSame(['id' => $id, 'active' => false], $command->getPayload());
        static::assertTrue($command->hasField('active'));
        static::assertTrue($command->isFailed());
    }

    public function testCommandWithoutPayloadIsInvalid(): void
    {
        $command = $this->createCommand([]);

        static::assertFalse($command->isValid());
        static::assertFalse($command->hasAnyField('id'));
        static::assertSame([], $command->getDecodedPrimaryKey());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createCommand(array $payload, ?EntityExistence $existence = null): InsertCommand
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [new BlogDefinition()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );

        $primaryKey = isset($payload['id']) && \is_string($payload['id']) ? ['id' => $payload['id']] : [];

        return new InsertCommand(
            $registry->get(BlogDefinition::class),
            $payload,
            $primaryKey,
            $existence ?? EntityExistence::createEmpty(),
            '/blog',
        );
    }
}
