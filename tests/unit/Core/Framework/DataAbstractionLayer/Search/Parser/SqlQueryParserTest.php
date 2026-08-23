<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Parser;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Contena\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\DateDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ListDefinition;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(SqlQueryParser::class)]
class SqlQueryParserTest extends TestCase
{
    public function testParseUnsupportedQueryFilter(): void
    {
        $this->expectException(DataAbstractionLayerException::class);

        $parser = new SqlQueryParser(new EntityDefinitionQueryHelper(), static::createStub(Connection::class));

        $parser->parse(
            new ScoreQuery(new ContainsFilter('description', 'test'), 250),
            new DateDefinition(),
            Context::createDefaultContext(),
        );
    }

    public function testParseNegatedEqualsAnyFilterKeepsNullableRows(): void
    {
        $parser = new SqlQueryParser(new EntityDefinitionQueryHelper(), static::createStub(Connection::class));

        $result = $parser->parse(
            new NotFilter(NotFilter::CONNECTION_AND, [
                new EqualsAnyFilter('dateNullable', ['2026-01-01']),
            ]),
            $this->getRegistry()->getByEntityName(DateDefinition::ENTITY_NAME),
            Context::createDefaultContext(),
        );

        static::assertCount(1, $result->getWheres());
        static::assertStringStartsWith('NOT ((', $result->getWheres()[0]);
        static::assertStringContainsString(' IN (:param_', $result->getWheres()[0]);
        static::assertStringContainsString('IS NOT NULL', $result->getWheres()[0]);

        $parameters = array_values($result->getParameters());
        static::assertCount(1, $parameters);
        static::assertSame(['2026-01-01'], $parameters[0]);
    }

    public function testParseEmptyEqualsAnyFilterOnListFieldMatchesNothing(): void
    {
        $parser = new SqlQueryParser(new EntityDefinitionQueryHelper(), static::createStub(Connection::class));

        $result = $parser->parse(
            new EqualsAnyFilter('data', []),
            $this->getRegistry([ListDefinition::class])->getByEntityName(ListDefinition::ENTITY_NAME),
            Context::createDefaultContext(),
        );

        static::assertSame(['1 = 0'], $result->getWheres());
        static::assertSame([], $result->getParameters());
    }

    /**
     * @param list<class-string<EntityDefinition>> $definitions
     */
    private function getRegistry(array $definitions = [DateDefinition::class]): DefinitionInstanceRegistry
    {
        return new StaticDefinitionInstanceRegistry(
            $definitions,
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }
}
