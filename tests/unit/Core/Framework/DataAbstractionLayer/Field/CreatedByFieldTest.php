<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Field\CreatedByField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CreatedByField::class)]
class CreatedByFieldTest extends TestCase
{
    public function testGetAllowedWriteScopesUsesExplicitScopes(): void
    {
        $field = new CreatedByField([Context::SYSTEM_SCOPE, Context::CRUD_API_SCOPE]);

        static::assertSame([Context::SYSTEM_SCOPE, Context::CRUD_API_SCOPE], $field->getAllowedWriteScopes());
    }

    public function testGetAllowedWriteScopesDefaultsToSystemAndCrudApiScopes(): void
    {
        $field = new CreatedByField();

        static::assertSame([Context::SYSTEM_SCOPE, Context::CRUD_API_SCOPE], $field->getAllowedWriteScopes());
    }

    public function testExplicitScopesStayUntouched(): void
    {
        $field = new CreatedByField([Context::SYSTEM_SCOPE]);

        static::assertSame([Context::SYSTEM_SCOPE], $field->getAllowedWriteScopes());
    }
}
