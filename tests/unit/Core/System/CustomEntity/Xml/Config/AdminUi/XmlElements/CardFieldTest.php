<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\CardField;

/**
 * @internal
 */
#[CoversClass(CardField::class)]
class CardFieldTest extends TestCase
{
    public function testFromXml(): void
    {
        $dom = new \DOMDocument();
        $cardFieldElement = $dom->createElement('field');
        $cardFieldElement->setAttribute('ref', 'cardField ref');

        $cardField = CardField::fromXml($cardFieldElement);
        static::assertSame('cardField ref', $cardField->getRef());
    }
}
