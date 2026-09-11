<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Manifest\Xml\Frontend;

use Contena\Core\Framework\App\Manifest\Xml\XmlElement;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal only for use by the app-system
 */
class Frontend extends XmlElement
{
    protected int $templateLoadPriority = 0;

    /** @var list<SeoUrl> */
    protected array $seoUrls = [];

    public function getTemplateLoadPriority(): int
    {
        return $this->templateLoadPriority;
    }

    /** @return list<SeoUrl> */
    public function getSeoUrls(): array
    {
        return $this->seoUrls;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = [];
        $seoUrls = [];

        foreach ($element->childNodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            if ($node->tagName === 'template-load-priority') {
                $values['templateLoadPriority'] = XmlUtils::phpize($node->textContent);
            }
            if ($node->tagName === 'seo-url') {
                $seoUrls[] = SeoUrl::fromXml($node);
            }
        }

        $values['seoUrls'] = $seoUrls;

        return $values;
    }
}
