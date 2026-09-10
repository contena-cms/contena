<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use Contena\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Detail;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Detail::class)]
class DetailTest extends TestCase
{
    public function testFromXml(): void
    {
        $dom = new \DOMDocument();
        $detailElement = $dom->createElement('detail');
        $tabsElement = $dom->createElement('tabs');

        $detailElement->appendChild(
            $tabsElement
        );

        $detail = Detail::fromXml($detailElement);
        $tabs = $detail->getTabs();
        static::assertSame([], $tabs->getContent());
    }
}
