<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Write\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Tag\TagDefinition;

/**
 * @internal
 */
#[CoversClass(WriteCommand::class)]
class WriteCommandTest extends TestCase
{
    public function testDetectsAnyPayloadFieldIncludingNull(): void
    {
        $id = Uuid::randomBytes();
        $command = new InsertCommand(
            new TagDefinition(),
            ['id' => $id, 'active' => null],
            ['id' => $id],
            EntityExistence::createEmpty(),
            '/tag',
        );

        static::assertTrue($command->hasAnyField('name', 'active'));
        static::assertFalse($command->hasAnyField('name', 'position'));
        static::assertFalse($command->hasAnyField());
    }
}
