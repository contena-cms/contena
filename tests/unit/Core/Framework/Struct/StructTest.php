<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Struct::class)]
class StructTest extends TestCase
{
    public function testGetApiAliasConvertsTheClassNameToSnakeCase(): void
    {
        static::assertSame(
            'contena_tests_unit_core_framework_struct_api_alias_example_struct',
            (new ApiAliasExampleStruct())->getApiAlias()
        );
    }
}

/**
 * @internal
 */
class ApiAliasExampleStruct extends Struct
{
}
