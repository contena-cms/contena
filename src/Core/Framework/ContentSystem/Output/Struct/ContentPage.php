<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Output\Struct;

use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigCanonicalizer;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\Visitor\PropertiesExtractionVisitor;
use Contena\Core\Framework\Struct\Struct;

/**
 * Layout metadata with fully hydrated element trees.
 *
 * @final
 */
class ContentPage extends Struct
{
    /**
     * @param iterable<ContentElement> $elements
     */
    public function __construct(
        public string $layoutId,
        public iterable $elements,
        public string $layoutName,
        public ?string $layoutVersion,
    ) {
    }

    public function getContentDecomposedPage(
        DataLoaderConfigSerializerProvider $configSerializerProvider,
        ConfigCanonicalizer $configCanonicalizer
    ): ContentDecomposedPage {
        $visitor = new PropertiesExtractionVisitor($configSerializerProvider, $configCanonicalizer);

        foreach ($this->elements as $element) {
            $clone = clone $element;
            $clone->traverse($visitor);
        }

        return new ContentDecomposedPage(
            ContentSkeletonElement::fromElements($this->elements),
            $visitor->getData(),
            $visitor->getAssignments(),
            $this->layoutId,
            $this->layoutName,
            $this->layoutVersion
        );
    }

    public function getContentSkeletonPage(): ContentSkeletonPage
    {
        return new ContentSkeletonPage(
            $this->layoutId,
            ContentSkeletonElement::fromElements($this->elements),
            $this->layoutName,
            $this->layoutVersion
        );
    }

    public function getContentDataPage(
        DataLoaderConfigSerializerProvider $configSerializerProvider,
        ConfigCanonicalizer $configCanonicalizer
    ): ContentDataPage {
        return $this->getContentDecomposedPage($configSerializerProvider, $configCanonicalizer)->getContentDataPage();
    }

    /**
     * @codeCoverageIgnore
     */
    public function getApiAlias(): string
    {
        return 'content_page';
    }
}
