<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\ContentSystem;

use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Loader\AbstractContentSystemStyleOptionLoader;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;

/**
 * Registers a single flat (breakpointAware=false) style option so the persistence tests can exercise the
 * flat write→DB→decode round-trip end-to-end — no shipped core option is flat. Wired only in the test
 * environment via the content_system.style_option_loader tag in services_test.xml.
 *
 * @internal
 *
 * @final
 */
class TestStyleOptionLoader extends AbstractContentSystemStyleOptionLoader
{
    public const FLAT_INTEGER = 'test-flat-span';

    public const SOURCE = 'test';

    public function load(): array
    {
        return [
            new StyleOptionSpecification(
                self::FLAT_INTEGER,
                new StyleOptionValueType('integer', null, null, null, null),
                false,
                null,
                self::SOURCE,
            ),
        ];
    }
}
