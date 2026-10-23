<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Mcp\Scenario;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 *
 * Validates that entity schemas contain the fields and associations
 * that the MCP admin_user stories depend on. Catches silent renames or removals.
 */
class SchemaCompletenessTest extends McpScenarioTestCase
{
    /**
     * @return iterable<string, array{string, list<string>, list<string>}>
     */
    public static function entitySchemaProvider(): iterable
    {
        yield 'order' => [
            'order',
            ['orderDateTime', 'amountTotal', 'orderNumber'],
            ['stateMachineState', 'orderUser', 'lineItems', 'deliveries', 'transactions', 'currency'],
        ];

        yield 'blog' => [
            'blog',
            ['stock', 'name', 'blogNumber', 'active'],
            ['categories', 'prices', 'tax'],
        ];

        yield 'user' => [
            'user',
            ['email', 'firstName', 'lastName', 'userNumber'],
            ['orderUsers', 'addresses', 'group'],
        ];

        yield 'flow' => [
            'flow',
            ['name', 'eventName', 'active'],
            ['sequences'],
        ];

        yield 'order_delivery' => [
            'order_delivery',
            [],
            ['stateMachineState'],
        ];

        yield 'order_transaction' => [
            'order_transaction',
            [],
            ['stateMachineState'],
        ];
    }

    /**
     * @param list<string> $expectedFields
     * @param list<string> $expectedAssociations
     */
    #[DataProvider('entitySchemaProvider')]
    public function testEntitySchemaContainsRequiredFieldsAndAssociations(
        string $entity,
        array $expectedFields,
        array $expectedAssociations,
    ): void {
        $output = ($this->entitySchemaTool)($entity);
        $data = $this->decodeToolOutput($output);

        $fieldNames = array_column($data['data']['fields'], 'name');
        foreach ($expectedFields as $field) {
            static::assertContains($field, $fieldNames, \sprintf(
                'Entity "%s" schema is missing field "%s" required by MCP admin_user stories.',
                $entity,
                $field,
            ));
        }

        $assocNames = array_column($data['data']['associations'], 'name');
        foreach ($expectedAssociations as $assoc) {
            static::assertContains($assoc, $assocNames, \sprintf(
                'Entity "%s" schema is missing association "%s" required by MCP admin_user stories.',
                $entity,
                $assoc,
            ));
        }
    }
}
