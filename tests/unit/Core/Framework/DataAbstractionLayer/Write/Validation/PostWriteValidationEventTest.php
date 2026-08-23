<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Write\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\DateDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\DateTimeDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\EmailDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ListDefinition;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @phpstan-type CommandConfig array{entityName: string, type: string, primaryKey: array<string, string>}
 *
 * @internal
 */
#[CoversClass(PostWriteValidationEvent::class)]
class PostWriteValidationEventTest extends TestCase
{
    private WriteContext $context;

    private StaticDefinitionInstanceRegistry $definitionInstanceRegistry;

    protected function setUp(): void
    {
        $this->context = WriteContext::createFromContext(Context::createDefaultContext());

        $this->definitionInstanceRegistry = new StaticDefinitionInstanceRegistry(
            [DateDefinition::class, ListDefinition::class, EmailDefinition::class, DateTimeDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }

    /**
     * @param CommandConfig[] $commands
     * @param array<string, array<array<string, string>>> $assertions
     */
    #[DataProvider('getPrimaryKeysProvider')]
    public function testGetPrimaryKeys(array $commands, array $assertions): void
    {
        $commands = $this->getCommands($commands);

        $event = new PostWriteValidationEvent($this->context, $commands);

        foreach ($assertions as $entity => $ids) {
            static::assertSame($ids, $event->getPrimaryKeys($entity), \sprintf('Primary keys for entity %s not match', $entity));
        }
    }

    /**
     * @param CommandConfig[] $commands
     * @param array<string, array<array<string, string>>> $assertions
     */
    #[DataProvider('getPrimaryKeysProvider')]
    public function testGetDeletedPrimaryKeysProvider(array $commands, array $assertions): void
    {
        $commands = $this->getCommands($commands);

        $event = new PostWriteValidationEvent($this->context, $commands);

        foreach ($assertions as $entity => $ids) {
            static::assertSame($ids, $event->getPrimaryKeys($entity), \sprintf('Primary keys for entity %s not match', $entity));
        }
    }

    /**
     * @param CommandConfig[] $commands
     * @param array<string, array<array<string, string>>> $assertions
     */
    #[DataProvider('getDeletedPrimaryKeysProvider')]
    public function testGetDeletedPrimaryKeys(array $commands, array $assertions): void
    {
        $commands = $this->getCommands($commands);

        $event = new PostWriteValidationEvent($this->context, $commands);

        foreach ($assertions as $entity => $ids) {
            static::assertSame($ids, $event->getDeletedPrimaryKeys($entity), \sprintf('Deleted primary keys for entity %s not match', $entity));
        }
    }

    public static function getPrimaryKeysProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield 'Test single insert' => [
            'commands' => [
                [
                    'entityName' => '_date_field_test',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('p1')],
                ],
            ],
            'assertions' => [
                '_date_field_test' => [['id' => $ids->getBytes('p1')]],
            ],
        ];

        yield 'Test multi insert' => [
            'commands' => [
                [
                    'entityName' => '_date_field_test',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('p1')],
                ],
                [
                    'entityName' => '_date_field_test',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('p2')],
                ],
                [
                    'entityName' => '_date_field_test',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('p3')],
                ],
                [
                    'entityName' => '_test_nullable',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('c1')],
                ],
                [
                    'entityName' => '_test_nullable',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('c2')],
                ],
                [
                    'entityName' => '_test_nullable',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('c3')],
                ],
                [
                    'entityName' => 'email',
                    'type' => 'insert',
                    'primaryKey' => ['language_id' => Defaults::LANGUAGE_SYSTEM, 'media_folder_id' => $ids->getBytes('c1')],
                ],
                [
                    'entityName' => 'email',
                    'type' => 'insert',
                    'primaryKey' => ['language_id' => Defaults::LANGUAGE_SYSTEM, 'media_folder_id' => $ids->getBytes('c2')],
                ],
                [
                    'entityName' => 'email',
                    'type' => 'insert',
                    'primaryKey' => ['language_id' => Defaults::LANGUAGE_SYSTEM, 'media_folder_id' => $ids->getBytes('c3')],
                ],
                [
                    'entityName' => 'date_time_test',
                    'type' => 'delete',
                    'primaryKey' => ['id' => $ids->getBytes('f1')],
                ],
                [
                    'entityName' => 'date_time_test',
                    'type' => 'delete',
                    'primaryKey' => ['id' => $ids->getBytes('f2')],
                ],
                [
                    'entityName' => 'date_time_test',
                    'type' => 'delete',
                    'primaryKey' => ['id' => $ids->getBytes('f3')],
                ],
            ],
            'assertions' => [
                '_date_field_test' => [
                    ['id' => $ids->getBytes('p1')],
                    ['id' => $ids->getBytes('p2')],
                    ['id' => $ids->getBytes('p3')],
                ],
                'date_time_test' => [
                    ['id' => $ids->getBytes('f1')],
                    ['id' => $ids->getBytes('f2')],
                    ['id' => $ids->getBytes('f3')],
                ],
                '_test_nullable' => [
                    ['id' => $ids->getBytes('c1')],
                    ['id' => $ids->getBytes('c2')],
                    ['id' => $ids->getBytes('c3')],
                ],
                'email' => [
                    ['language_id' => Defaults::LANGUAGE_SYSTEM, 'media_folder_id' => $ids->getBytes('c1')],
                    ['language_id' => Defaults::LANGUAGE_SYSTEM, 'media_folder_id' => $ids->getBytes('c2')],
                    ['language_id' => Defaults::LANGUAGE_SYSTEM, 'media_folder_id' => $ids->getBytes('c3')],
                ],
                'not-found' => [],
            ],
        ];
    }

    public static function getDeletedPrimaryKeysProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield 'Test single delete' => [
            'commands' => [
                [
                    'entityName' => '_date_field_test',
                    'type' => 'delete',
                    'primaryKey' => ['id' => $ids->getBytes('p1')],
                ],
            ],
            'assertions' => [
                '_date_field_test' => [
                    ['id' => $ids->getBytes('p1')],
                ],
                'not-found' => [],
            ],
        ];

        yield 'Test multi insert' => [
            'commands' => [
                [
                    'entityName' => '_date_field_test',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('p1')],
                ],
                [
                    'entityName' => '_date_field_test',
                    'type' => 'delete',
                    'primaryKey' => ['id' => $ids->getBytes('p2')],
                ],
                [
                    'entityName' => '_date_field_test',
                    'type' => 'delete',
                    'primaryKey' => ['id' => $ids->getBytes('p3')],
                ],
                [
                    'entityName' => '_test_nullable',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('c1')],
                ],
                [
                    'entityName' => '_test_nullable',
                    'type' => 'delete',
                    'primaryKey' => ['id' => $ids->getBytes('c2')],
                ],
                [
                    'entityName' => '_test_nullable',
                    'type' => 'delete',
                    'primaryKey' => ['id' => $ids->getBytes('c3')],
                ],
                [
                    'entityName' => 'email',
                    'type' => 'delete',
                    'primaryKey' => ['language_id' => Defaults::LANGUAGE_SYSTEM, 'media_folder_id' => $ids->getBytes('c1')],
                ],
                [
                    'entityName' => 'email',
                    'type' => 'insert',
                    'primaryKey' => ['language_id' => Defaults::LANGUAGE_SYSTEM, 'media_folder_id' => $ids->getBytes('c2')],
                ],
                [
                    'entityName' => 'email',
                    'type' => 'delete',
                    'primaryKey' => ['language_id' => Defaults::LANGUAGE_SYSTEM, 'media_folder_id' => $ids->getBytes('c3')],
                ],
                [
                    'entityName' => 'date_time_test',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('f1')],
                ],
                [
                    'entityName' => 'date_time_test',
                    'type' => 'delete',
                    'primaryKey' => ['id' => $ids->getBytes('f2')],
                ],
                [
                    'entityName' => 'date_time_test',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('f3')],
                ],
            ],
            'assertions' => [
                '_date_field_test' => [
                    ['id' => $ids->getBytes('p2')],
                    ['id' => $ids->getBytes('p3')],
                ],
                '_test_nullable' => [
                    ['id' => $ids->getBytes('c2')],
                    ['id' => $ids->getBytes('c3')],
                ],
                'email' => [
                    ['language_id' => Defaults::LANGUAGE_SYSTEM, 'media_folder_id' => $ids->getBytes('c1')],
                    ['language_id' => Defaults::LANGUAGE_SYSTEM, 'media_folder_id' => $ids->getBytes('c3')],
                ],
                'date_time_test' => [
                    ['id' => $ids->getBytes('f2')],
                ],
                'not-found' => [],
            ],
        ];
    }

    /**
     * @param CommandConfig[] $commandsArray
     *
     * @return list<WriteCommand>
     */
    private function getCommands(array $commandsArray): array
    {
        $commands = [];

        $existence = new EntityExistence('', [], false, false, false, []);

        foreach ($commandsArray as $command) {
            $definition = $this->definitionInstanceRegistry->getByEntityName($command['entityName']);
            $primaryKey = $command['primaryKey'];

            switch ($command['type']) {
                case 'insert':
                    $commands[] = new InsertCommand($definition, [], $primaryKey, $existence, '');
                    break;
                case 'delete':
                    $commands[] = new DeleteCommand($definition, $primaryKey, $existence);
                    break;
            }
        }

        return $commands;
    }
}
