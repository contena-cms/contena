<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Elasticsearch\ResolvedField;

/**
 * @internal
 */
#[CoversClass(ResolvedField::class)]
class ResolvedFieldTest extends TestCase
{
    public function testResolvedFieldGetters(): void
    {
        $stringField = new StringField('name', 'name');
        $resolved = new ResolvedField($stringField, 'tags');

        static::assertSame($stringField, $resolved->getResolvedField());
        static::assertSame('tags', $resolved->getRoot());
    }

    public function testResolvedFieldRootDefaultsToNull(): void
    {
        $stringField = new StringField('name', 'name');
        $resolved = new ResolvedField($stringField);

        static::assertNull($resolved->getRoot());
    }
}
