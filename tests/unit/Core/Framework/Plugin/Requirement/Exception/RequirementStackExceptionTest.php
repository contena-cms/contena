<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Plugin\Requirement\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Plugin\Requirement\Exception\MissingRequirementException;
use Contena\Core\Framework\Plugin\Requirement\Exception\RequirementStackException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(RequirementStackException::class)]
class RequirementStackExceptionTest extends TestCase
{
    #[TestDox('the message aggregates method, failure count and every inner message')]
    public function testMessageAggregation(): void
    {
        $exception = new RequirementStackException(
            'install',
            new MissingRequirementException('contena/core', '~6.8'),
            new MissingRequirementException('ct/paypal', '*'),
        );

        static::assertSame('FRAMEWORK__PLUGIN_REQUIREMENTS_FAILED', $exception->getErrorCode());
        static::assertSame(Response::HTTP_FAILED_DEPENDENCY, $exception->getStatusCode());
        static::assertCount(2, $exception->getRequirements());
        static::assertStringContainsString('Could not install plugin, got 2 failure(s).', $exception->getMessage());
        static::assertStringContainsString('Required plugin/package "contena/core ~6.8" is missing', $exception->getMessage());
        static::assertStringContainsString('Required plugin/package "ct/paypal *" is missing', $exception->getMessage());
    }

    #[TestDox('getErrors yields the inner requirement errors')]
    public function testGetErrors(): void
    {
        $exception = new RequirementStackException(
            'update',
            new MissingRequirementException('contena/core', '~6.8'),
        );

        $errors = iterator_to_array($exception->getErrors(), false);

        static::assertCount(1, $errors);
        static::assertSame('FRAMEWORK__PLUGIN_REQUIREMENT_MISSING', $errors[0]['code']);
        static::assertStringContainsString('contena/core ~6.8', (string) $errors[0]['detail']);
    }
}
