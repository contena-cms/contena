<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Validation\Constraint;

use Contena\Core\Framework\FrameworkException;
use Contena\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Contena\Core\Framework\Validation\Constraint\Uuid;
use Contena\Core\Framework\Validation\Constraint\UuidValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(UuidValidator::class)]
class UuidValidatorTest extends TestCase
{
    public function testValidateThrowsExceptionBecauseConstraintHasWrongClass(): void
    {
        $wrongConstraint = new ArrayOfUuid();
        $this->expectExceptionObject(FrameworkException::unexpectedType($wrongConstraint, Uuid::class));
        $validator = new UuidValidator();
        $validator->validate([], $wrongConstraint);
    }
}
