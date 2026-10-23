<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Mcp\Scenario;

use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class BlogContentScenarioTest extends McpScenarioTestCase
{
    public function testUS22BlogsWithoutMedia(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $blogWithMedia = (new BlogBuilder($ids, 'with-media'))
            ->price(10.00)
            ->media('test-media-id')
            ->build();

        $blogWithoutMedia = (new BlogBuilder($ids, 'without-media'))
            ->price(10.00)
            ->build();

        static::getContainer()->get('blog.repository')->create([$blogWithMedia, $blogWithoutMedia], $context);

        $output = ($this->entitySearchTool)(
            entity: 'blog',
            criteria: json_encode([
                'filter' => [
                    ['type' => 'equals', 'field' => 'media.id', 'value' => null],
                ],
            ], \JSON_THROW_ON_ERROR),
        );

        $data = $this->decodeToolOutput($output);

        $foundIds = array_column($data['data'], 'id');
        static::assertContains($ids->get('without-media'), $foundIds, 'Blog without media should appear');
        static::assertNotContains($ids->get('with-media'), $foundIds, 'Blog with media should not appear');
    }

    public function testUS23PriceUpdate(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $blog = (new BlogBuilder($ids, 'price-update'))
            ->price(19.99)
            ->build();

        static::getContainer()->get('blog.repository')->create([$blog], $context);

        $output = ($this->entityUpsertTool)(
            entity: 'blog',
            payload: json_encode([
                'id' => $ids->get('price-update'),
                'price' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'gross' => 39.99,
                        'net' => 33.61,
                        'linked' => true,
                    ],
                ],
            ], \JSON_THROW_ON_ERROR),
            dryRun: false,
        );

        $data = $this->decodeToolOutput($output);
        static::assertFalse($data['_meta']['dryRun']);

        $readOutput = ($this->entityReadTool)(
            entity: 'blog',
            id: $ids->get('price-update'),
        );

        $readData = $this->decodeToolOutput($readOutput);
        static::assertSame(39.99, $readData['data']['price'][0]['gross']);
    }

    public function testUS24CategoryAssignment(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $categoryId = Uuid::randomHex();
        static::getContainer()->get('category.repository')->create([
            ['id' => $categoryId, 'name' => 'MCP Test Category US24'],
        ], $context);

        $blog = (new BlogBuilder($ids, 'cat-assign'))
            ->price(10.00)
            ->visibility(TestDefaults::CHANNEL)
            ->build();

        static::getContainer()->get('blog.repository')->create([$blog], $context);

        $output = ($this->entityUpsertTool)(
            entity: 'blog',
            payload: json_encode([
                'id' => $ids->get('cat-assign'),
                'categories' => [['id' => $categoryId]],
            ], \JSON_THROW_ON_ERROR),
            dryRun: false,
        );

        $this->decodeToolOutput($output);

        $readOutput = ($this->entityReadTool)(
            entity: 'blog',
            id: $ids->get('cat-assign'),
            criteria: json_encode([
                'associations' => ['categories' => []],
            ], \JSON_THROW_ON_ERROR),
        );

        $readData = $this->decodeToolOutput($readOutput);
        $categoryIds = array_column($readData['data']['categories'], 'id');
        static::assertContains($categoryId, $categoryIds, 'Blog should be assigned to the category');
    }
}
