<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Demodata\Faker;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Demodata\Faker\Commerce;

/**
 * @internal
 */
#[CoversClass(Commerce::class)]
class CommerceTest extends TestCase
{
    public function testCustomFieldSetReplacesSpacesWithUnderscores(): void
    {
        // pins the adjective list to a single entry with spaces, the list is read through late static binding
        $commerce = new class(Factory::create()) extends Commerce {
            // @phpstan-ignore contena.propertyNativeType (redeclares an untyped parent property, PHP forbids adding a type)
            protected static $blogName = ['adjective' => ['Test Blog Name']];
        };

        $setName = $commerce->customFieldSet();

        static::assertStringNotContainsString(' ', $setName);
        static::assertStringStartsWith('Test_Blog_Name_', $setName);
    }
}
