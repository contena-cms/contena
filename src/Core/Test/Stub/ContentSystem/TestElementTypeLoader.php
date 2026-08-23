<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\ContentSystem;

use Contena\Core\Framework\ContentSystem\Layout\Type\Loader\AbstractContentSystemElementTypeLoader;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;

/**
 * Registers three deterministic element types for the resolvability-gate and default-materialization tests,
 * independent of the shipped type definitions: a property-free component that is resolvable against every binding,
 * a component with a required reference to {@see UnresolvableContextTarget} that is resolvable against none, and a
 * component with a required primitive carrying a type default (used to prove the write-boundary default seeding).
 * Wired only in the test environment via the content_system.type_loader tag in services_test.xml.
 *
 * @internal
 *
 * @final
 */
class TestElementTypeLoader extends AbstractContentSystemElementTypeLoader
{
    public const RESOLVABLE = 'CT:Test:Resolvable';

    public const UNRESOLVABLE = 'CT:Test:RequiresEntity';

    public const DEFAULTED_PRIMITIVE = 'CT:Test:DefaultedPrimitive';

    public const SOURCE = 'test';

    public function load(): array
    {
        return [
            new ContentSystemElementTypeSpecification(
                self::RESOLVABLE,
                'Resolvable test element',
                '',
                null,
                null,
                new CopilotSpecification('', []),
                [],
                [],
                self::SOURCE,
            ),
            new ContentSystemElementTypeSpecification(
                self::UNRESOLVABLE,
                'Unresolvable test element',
                '',
                null,
                null,
                new CopilotSpecification('', []),
                [
                    'target' => new PropertySpecification(
                        'target',
                        new PropertyType(UnresolvableContextTarget::class, false, null, null),
                        true,
                        '',
                        '',
                        null,
                    ),
                ],
                [],
                self::SOURCE,
            ),
            new ContentSystemElementTypeSpecification(
                self::DEFAULTED_PRIMITIVE,
                'Defaulted primitive test element',
                '',
                null,
                null,
                new CopilotSpecification('', []),
                [
                    'headline' => new PropertySpecification(
                        'headline',
                        new PropertyType('string', false, null, 'Seeded headline'),
                        true,
                        '',
                        '',
                        null,
                    ),
                ],
                [],
                self::SOURCE,
            ),
        ];
    }
}
