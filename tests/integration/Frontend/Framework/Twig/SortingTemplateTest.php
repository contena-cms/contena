<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Twig;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingCollection;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingEntity;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class SortingTemplateTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * The `score` sorting is locked, but its translations are editable in the
     * administration, so its label must not be replaced by a snippet.
     */
    public function testScoreSortingKeepsItsTranslatedLabel(): void
    {
        $output = $this->renderSortings(new BlogSortingCollection([
            $this->createSorting(key: 'score', label: 'Label configured in the administration'),
        ]));

        static::assertStringContainsString('<option value="score">Label configured in the administration</option>', $output);
        static::assertStringNotContainsString('Top results', $output);
    }

    public function testConfigurableSortingKeepsItsTranslatedLabel(): void
    {
        $output = $this->renderSortings(new BlogSortingCollection([
            $this->createSorting(key: 'name-asc', label: 'Name A-Z'),
        ]));

        static::assertStringContainsString('<option value="name-asc">Name A-Z</option>', $output);
    }

    private function renderSortings(BlogSortingCollection $sortings): string
    {
        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        return FrontendTwigRenderer::render(static::getContainer(), '@Frontend/frontend/component/sorting.html.twig', [
            'current' => '',
            'sortings' => $sortings,
        ], $context);
    }

    private function createSorting(string $key, string $label): BlogSortingEntity
    {
        $sorting = new BlogSortingEntity();
        $sorting->setUniqueIdentifier(Uuid::randomHex());
        $sorting->setKey($key);
        $sorting->setTranslated(['label' => $label]);

        return $sorting;
    }
}
