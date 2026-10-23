<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Mcp\Scenario;

use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class AdminDataExplorationScenarioTest extends McpScenarioTestCase
{
    public function testUS2LowStockBlogs(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $blogs = [
            (new BlogBuilder($ids, 'p-high'))->price(100)->stock(10)->build(),
            (new BlogBuilder($ids, 'p-low1'))->price(50)->stock(3)->build(),
            (new BlogBuilder($ids, 'p-low2'))->price(25)->stock(1)->build(),
        ];

        static::getContainer()->get('blog.repository')->create($blogs, $context);

        $output = ($this->entitySearchTool)('blog', json_encode([
            'filter' => [
                ['type' => 'range', 'field' => 'stock', 'parameters' => ['lt' => 5]],
                ['type' => 'multi', 'operator' => 'OR', 'queries' => [
                    ['type' => 'equals', 'field' => 'id', 'value' => $ids->get('p-low1')],
                    ['type' => 'equals', 'field' => 'id', 'value' => $ids->get('p-low2')],
                    ['type' => 'equals', 'field' => 'id', 'value' => $ids->get('p-high')],
                ]],
            ],
        ], \JSON_THROW_ON_ERROR));

        $data = $this->decodeToolOutput($output);

        static::assertSame(2, $data['_meta']['total']);
        foreach ($data['data'] as $blog) {
            static::assertLessThan(5, $blog['stock']);
        }
    }

    public function testSearchWithBlogsAndAssociationsReturnsInlineResult(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $blogs = [];
        for ($i = 0; $i < 3; ++$i) {
            $blogs[] = (new BlogBuilder($ids, "prod-$i"))
                ->price(100)
                ->stock(10)
                ->manufacturer('manufacturer')
                ->property('red', 'color')
                ->property('XL', 'size')
                ->build();
        }

        static::getContainer()->get('blog.repository')->create($blogs, $context);

        $criteria = json_encode([
            'ids' => array_map(fn (int $i) => $ids->get("prod-$i"), range(0, 2)),
            'associations' => [
                'properties' => ['associations' => ['group' => new \stdClass()]],
                'manufacturer' => new \stdClass(),
            ],
        ], \JSON_THROW_ON_ERROR);

        $output = ($this->entitySearchTool)('blog', $criteria);
        $data = $this->decodeToolOutput($output);

        static::assertCount(3, $data['data']);
        static::assertSame(3, $data['_meta']['total']);
        static::assertArrayNotHasKey('truncated', $data['_meta']);
    }

    public function testUS3UserEntitySchema(): void
    {
        $output = ($this->entitySchemaTool)('user');
        $data = $this->decodeToolOutput($output);

        $fieldNames = array_column($data['data']['fields'], 'name');
        static::assertContains('email', $fieldNames);
        static::assertContains('firstName', $fieldNames);
        static::assertContains('lastName', $fieldNames);
        static::assertContains('userNumber', $fieldNames);

        $assocNames = array_column($data['data']['associations'], 'name');
        static::assertContains('orderUsers', $assocNames);
        static::assertContains('addresses', $assocNames);
        static::assertContains('group', $assocNames);
    }
}
