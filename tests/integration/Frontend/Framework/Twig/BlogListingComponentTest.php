<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Twig;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingCollection;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingEntity;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\SlotSpecification;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * @internal
 */
class BlogListingComponentTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * The component reads `app.request` for the layout query parameter, which throws without a request
     * on the stack.
     */
    protected function setUp(): void
    {
        $this->requestStack()->push(new Request());
    }

    protected function tearDown(): void
    {
        $this->requestStack()->pop();
    }

    /**
     * Filters are a separate Ct:Filter:Panel element, so the listing can sit in one grid column with the panel
     * in another without rendering a second panel of its own.
     */
    public function testRendersNoFilterUiOfItsOwn(): void
    {
        $html = $this->render(['listing' => $this->listing()]);

        static::assertStringContainsString('data-component="Ct:Blog:Listing"', $html);
        static::assertStringNotContainsString('data-component="Ct:Filter:Panel"', $html);
        static::assertStringNotContainsString('data-component="Ct:Filter:ActiveFilters"', $html);
    }

    /**
     * The type must not advertise slots the template no longer renders, because Studio would offer
     * drop targets that silently swallow whatever is placed into them.
     */
    public function testDeclaresNoFilterSlots(): void
    {
        $types = static::getContainer()->get(ContentSystemElementTypeRegistry::class);
        static::assertInstanceOf(AbstractContentSystemElementTypeRegistry::class, $types);

        $slots = array_map(
            static fn (SlotSpecification $slot): string => $slot->name(),
            $types->get('Ct:Blog:Listing')->slots()
        );

        static::assertSame([
            'blog-grid',
            'pagination',
        ], $slots);
    }

    /**
     * The sorting select and the layout switch belong to the listing, so the result count sits with them
     * rather than in the filter panel.
     */
    public function testRendersTheSortingActionsAndResultCount(): void
    {
        $html = $this->render(['listing' => $this->listing(42)]);

        static::assertStringContainsString('ct-blog-listing__actions', $html);
        static::assertStringContainsString('data-component="Ct:Blog:Sorting"', $html);
        static::assertStringContainsString('Newest first', $html);
        static::assertStringContainsString('data-component="Ct:Blog:LayoutSwitch"', $html);
        static::assertStringContainsString('42', $html);
    }

    /**
     * One toggle off per render: turning both off at once would still pass if the layout switch were guarded
     * by `showSorting`.
     */
    public function testEachActionToggleHidesOnlyItsOwnControl(): void
    {
        $withoutSorting = $this->render(['listing' => $this->listing(42), 'showSorting' => false]);

        static::assertStringNotContainsString('data-component="Ct:Blog:Sorting"', $withoutSorting);
        static::assertStringContainsString('data-component="Ct:Blog:LayoutSwitch"', $withoutSorting);

        $withoutLayoutSwitch = $this->render(['listing' => $this->listing(42), 'showLayoutSwitch' => false]);

        static::assertStringContainsString('data-component="Ct:Blog:Sorting"', $withoutLayoutSwitch);
        static::assertStringNotContainsString('data-component="Ct:Blog:LayoutSwitch"', $withoutLayoutSwitch);
    }

    private function requestStack(): RequestStack
    {
        $requestStack = static::getContainer()->get('request_stack');
        static::assertInstanceOf(RequestStack::class, $requestStack);

        return $requestStack;
    }

    /**
     * @param array<string, mixed> $props
     */
    private function render(array $props): string
    {
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        return $twig
            ->createTemplate('{{ component(\'Ct:Blog:Listing\', props) }}')
            ->render(['props' => $props]);
    }

    private function listing(int $total = 0): BlogListingResult
    {
        $result = new EntitySearchResult(
            $total,
            new BlogCollection(),
            new AggregationResultCollection(),
            new Criteria(),
            Context::createDefaultContext()
        );

        $sorting = new BlogSortingEntity();
        $sorting->setId(Uuid::randomHex());
        $sorting->setKey('newest-first');
        $sorting->setTranslated(['label' => 'Newest first']);

        return BlogListingResult::fromSearchResult($result, new BlogSortingCollection([$sorting]));
    }
}
