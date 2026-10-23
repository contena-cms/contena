<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Flow\Event\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Flow\Event\Xml\CustomEvent;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 */
#[CoversClass(CustomEvent::class)]
class CustomEventTest extends TestCase
{
    public function testFromXml(): void
    {
        $doc = XmlUtils::loadFile(
            __DIR__ . '/../../../_fixtures/Resources/flow-event-with-events.xml',
            __DIR__ . '/../../../../../../../../src/Core/Framework/App/Flow/Schema/flow-1.0.xsd'
        );

        $expected = [
            'name' => 'checkout.order.place.custom',
            'aware' => ['orderAware', 'userAware'],
        ];

        $events = $doc->getElementsByTagName('flow-events')->item(0);
        static::assertNotNull($events);
        foreach ($events->getElementsByTagName('flow-event') as $event) {
            $result = CustomEvent::fromXml($event);
            $result = $result->toArray('en-GB');
            static::assertSame($expected, $result);
        }
    }
}
