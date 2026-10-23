<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Validation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\FrameworkException;
use Contena\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Contena\Core\Framework\Validation\Constraint\ArrayOfUuidValidator;
use Contena\Core\Framework\Validation\Constraint\Uuid;

/**
 * @internal
 */
#[CoversClass(ArrayOfUuidValidator::class)]
class ArrayOfUuidValidatorTest extends TestCase
{
    public function testValidateThrowsExceptionBecauseConstraintHasWrongClass(): void
    {
        $wrongConstraint = new Uuid();
        $this->expectExceptionObject(FrameworkException::unexpectedType($wrongConstraint, ArrayOfUuid::class));
        $validator = new ArrayOfUuidValidator();
        $validator->validate([], $wrongConstraint);
    }
}
