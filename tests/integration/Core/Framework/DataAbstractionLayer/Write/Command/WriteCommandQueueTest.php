<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Write\Command;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\JsonUpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Country\CountryDefinition;
use Contena\Core\System\Region\RegionDefinition;

/**
 * @internal
 */
class WriteCommandQueueTest extends TestCase
{
    use KernelTestBehaviour;

    public function testCommandsInOrder(): void
    {
        /** @var DefinitionInstanceRegistry */
        $definitionRegistry = static::getContainer()->get(DefinitionInstanceRegistry::class);

        $queue = new WriteCommandQueue();

        $countryId = Uuid::randomHex();
        $countryDefinition = $definitionRegistry->getByEntityName(CountryDefinition::ENTITY_NAME);
        $countryPayload = [
            'id' => Uuid::fromHexToBytes($countryId),
        ];

        $regionId = Uuid::randomHex();
        $regionDefinition = $definitionRegistry->getByEntityName(RegionDefinition::ENTITY_NAME);
        $regionPayload = [
            'id' => Uuid::fromHexToBytes($regionId),
            'country_id' => Uuid::fromHexToBytes($countryId),
        ];

        $queue->add($regionDefinition->getEntityName(), $regionId, new UpdateCommand(
            $regionDefinition,
            $regionPayload,
            [
                'id' => Uuid::fromHexToBytes($regionId),
            ],
            new EntityExistence(
                $regionDefinition->getEntityName(),
                [
                    'id' => $regionId,
                ],
                true,
                false,
                false,
                $regionPayload
            ),
            '/0/regions/0'
        ));

        $queue->add($regionDefinition->getEntityName(), $regionId, new JsonUpdateCommand(
            $regionDefinition,
            'custom_fields',
            [
                'test' => 'test',
            ],
            [
                'id' => Uuid::fromHexToBytes($regionId),
            ],
            new EntityExistence(
                $regionDefinition->getEntityName(),
                [
                    'id' => $regionId,
                ],
                true,
                false,
                false,
                $regionPayload
            ),
            '/0/regions/0'
        ));

        $queue->add($regionDefinition->getEntityName(), $regionId, new InsertCommand(
            $regionDefinition,
            $regionPayload,
            [
                'id' => Uuid::fromHexToBytes($regionId),
            ],
            new EntityExistence(
                $regionDefinition->getEntityName(),
                [
                    'id' => $regionId,
                ],
                false,
                false,
                false,
                $regionPayload
            ),
            '/0/regions/0'
        ));

        $queue->add($countryDefinition->getEntityName(), $countryId, new InsertCommand(
            $countryDefinition,
            $countryPayload,
            [
                'id' => Uuid::fromHexToBytes($countryId),
            ],
            new EntityExistence(
                $countryDefinition->getEntityName(),
                [
                    'id' => $countryId,
                ],
                false,
                false,
                false,
                $countryPayload
            ),
            '/0'
        ));

        $ordered = $queue->getCommandsInOrder($definitionRegistry);

        static::assertCount(4, $ordered);

        static::assertInstanceOf(InsertCommand::class, $ordered[0]);
        static::assertSame($countryDefinition->getEntityName(), $ordered[0]->getEntityName());

        static::assertInstanceOf(InsertCommand::class, $ordered[1]);
        static::assertSame($regionDefinition->getEntityName(), $ordered[1]->getEntityName());

        static::assertTrue($ordered[2] instanceof JsonUpdateCommand || $ordered[2] instanceof UpdateCommand);
        static::assertTrue($ordered[3] instanceof JsonUpdateCommand || $ordered[3] instanceof UpdateCommand);
    }
}
