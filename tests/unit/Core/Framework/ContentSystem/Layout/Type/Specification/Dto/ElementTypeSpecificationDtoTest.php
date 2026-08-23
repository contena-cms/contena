<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Specification\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\CopilotSpecificationDto;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\ElementTypeSpecificationDto;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\PropertySpecificationDto;

/**
 * @internal
 */
#[CoversClass(ElementTypeSpecificationDto::class)]
class ElementTypeSpecificationDtoTest extends TestCase
{
    #[TestDox('uses outer array key as property key in specification')]
    public function testUsesOuterArrayKeyAsPropertyKey(): void
    {
        $dto = new ElementTypeSpecificationDto(
            'Blog Card',
            'A blog card.',
            null,
            null,
            new CopilotSpecificationDto('Summary.', []),
            [
                'custom_key' => new PropertySpecificationDto('different_name', 'string', true, false, 'Title', '', null, null, null),
                'second_key' => new PropertySpecificationDto('other_name', 'string', false, false, 'Other', '', null, null, null),
            ],
            [],
        );

        $schema = $dto->toContentSystemElementTypeSpecification('CT:Blog:Card', 'core')->toSchema();

        static::assertArrayHasKey('custom_key', $schema['properties']);
        static::assertArrayNotHasKey('different_name', $schema['properties']);
        static::assertCount(2, $schema['properties']);
    }

    #[TestDox('produces empty properties and slots when none provided')]
    public function testProducesEmptyCollectionsWhenNoneProvided(): void
    {
        $schema = $this->buildMinimalDto()->toContentSystemElementTypeSpecification('CT:Empty', 'core')->toSchema();

        static::assertSame([], $schema['properties']);
        static::assertSame([], $schema['slots']);
    }

    private function buildMinimalDto(): ElementTypeSpecificationDto
    {
        return new ElementTypeSpecificationDto(
            'Test',
            'A test element.',
            null,
            null,
            new CopilotSpecificationDto('Test.', []),
            [],
            [],
        );
    }
}
