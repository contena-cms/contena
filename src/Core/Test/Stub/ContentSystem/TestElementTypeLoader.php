<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\ContentSystem;

use Contena\Core\Framework\ContentSystem\Layout\Type\Loader\AbstractContentSystemElementTypeLoader;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;

/**
 * Registers five deterministic element types for the resolvability-gate and default-materialization tests,
 * independent of the shipped type definitions: a property-free component that is resolvable against every binding,
 * a component with a required reference to {@see UnresolvableContextTarget} that is resolvable against none, a
 * component with a required primitive carrying a type default (used to prove the write-boundary default seeding),
 * and a component with a required translatable primitive carrying no default, which no shipped type declares —
 * every shipped translatable property is optional, so the anchor-entry satisfaction rule has no shipped subject.
 * The fifth is that same required translatable primitive with a type default, the corner where a reseeded default
 * is stored as the language map {@see PropertyType::storedDefault()} keys under the anchor language rather than
 * as a bare scalar.
 * Wired only in the test environment via the content_system.type_loader tag in services_test.php.
 *
 * @final
 */
class TestElementTypeLoader extends AbstractContentSystemElementTypeLoader
{
    public const RESOLVABLE = 'Ct:Test:Resolvable';

    public const UNRESOLVABLE = 'Ct:Test:RequiresEntity';

    public const DEFAULTED_PRIMITIVE = 'Ct:Test:DefaultedPrimitive';

    public const TRANSLATABLE_REQUIRED = 'Ct:Test:TranslatableRequired';

    public const DEFAULTED_TRANSLATABLE = 'Ct:Test:DefaultedTranslatable';

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
            new ContentSystemElementTypeSpecification(
                self::TRANSLATABLE_REQUIRED,
                'Required translatable test element',
                '',
                null,
                null,
                new CopilotSpecification('', []),
                [
                    'label' => new PropertySpecification(
                        'label',
                        new PropertyType('string', true, null, null),
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
                self::DEFAULTED_TRANSLATABLE,
                'Defaulted translatable test element',
                '',
                null,
                null,
                new CopilotSpecification('', []),
                [
                    'tagline' => new PropertySpecification(
                        'tagline',
                        new PropertyType('string', true, null, 'Seeded tagline'),
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
