<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Twig;

use Contena\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Contena\Core\Framework\ContentSystem\Binding\Registry\ContentSystemBindingSpecificationRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

/**
 * @internal
 */
class FilterPanelComponentTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * The panel takes the listing from root context rather than loading its own, so a panel beside a blog
     * listing is one load rather than two. A `resolvedBy` here would be fill-applied on insert, and the
     * consumer mirror skips an element whose key already has a data requirement — restoring the second load.
     */
    public function testTheListingIsReceivedAsContextRatherThanLoaded(): void
    {
        $registry = static::getContainer()->get(ContentSystemBindingSpecificationRegistry::class);
        static::assertInstanceOf(AbstractContentSystemBindingSpecificationRegistry::class, $registry);

        static::assertArrayNotHasKey('core:Ct:Filter:Panel', $registry->all());
        static::assertSame([], $registry->byType('Ct:Filter:Panel'));
    }

    /**
     * Optional, so a panel on a layout whose root context has no listing renders filterless instead of failing
     * the write boundary's resolvability gate.
     */
    public function testTheListingPropertyIsOptional(): void
    {
        static::assertFalse($this->properties()['blogListing']->required());
    }

    /**
     * The panel owns the summary, so a panel in one grid column takes its chips along instead of leaving them
     * stranded next to the blog grid. It has to be a descendant, not a second root: Ct:Grid:Container lays
     * its children out as grid items, so a second root element would claim the next cell and push the
     * neighbouring element onto a new row.
     */
    public function testRendersTheSummaryInsideItsSingleRootElement(): void
    {
        $html = $this->render([]);

        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<!DOCTYPE html><html><body>' . $html . '</body></html>');
        libxml_clear_errors();

        $body = $document->getElementsByTagName('body')->item(0);
        static::assertInstanceOf(\DOMElement::class, $body);

        $roots = [];
        foreach ($body->childNodes as $node) {
            if ($node instanceof \DOMElement) {
                $roots[] = $node;
            }
        }

        static::assertCount(1, $roots);
        static::assertStringContainsString('ct-filter-panel', $roots[0]->getAttribute('class'));

        $summaries = new \DOMXPath($document)->query('//*[@data-component="Ct:Filter:ActiveFilters"]');
        static::assertInstanceOf(\DOMNodeList::class, $summaries);
        static::assertCount(1, $summaries);
    }

    /**
     * Degrading to a filterless panel instead of failing the render matches Ct:Media:Image.
     */
    public function testRendersWithoutFiltersWhenTheListingIsMissing(): void
    {
        $html = $this->render([]);

        static::assertStringContainsString('data-component="Ct:Filter:Panel"', $html);
        static::assertStringNotContainsString('data-component="Ct:Filter:Item"', $html);
    }

    /**
     * @return array<string, PropertySpecification>
     */
    private function properties(): array
    {
        $types = static::getContainer()->get(ContentSystemElementTypeRegistry::class);
        static::assertInstanceOf(AbstractContentSystemElementTypeRegistry::class, $types);

        return $types->get('Ct:Filter:Panel')->properties();
    }

    /**
     * @param array<string, mixed> $props
     */
    private function render(array $props): string
    {
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        return $twig
            ->createTemplate('{{ component(\'Ct:Filter:Panel\', props) }}')
            ->render(['props' => $props]);
    }
}
