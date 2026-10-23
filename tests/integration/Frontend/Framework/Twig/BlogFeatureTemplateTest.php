<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Twig;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Content\Blog\Aggregate\BlogFeatureSet\BlogFeatureSetDefinition;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 */
class BlogFeatureTemplateTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    public function testTextCustomFieldIsRendered(): void
    {
        $output = $this->renderFeature(CustomFieldTypes::TEXT, ['content' => 'wood']);

        static::assertStringContainsString('Material:', $output);
        static::assertStringContainsString('wood', $output);
    }

    public function testSelectCustomFieldRendersTheResolvedLabels(): void
    {
        $output = $this->renderFeature(CustomFieldTypes::SELECT, [
            'content' => ['oak', 'pine'],
            'display' => ['Oak', 'Pine'],
        ]);

        static::assertStringContainsString('Material:', $output);
        static::assertStringContainsString('Oak, Pine', $output);
    }

    public function testPriceCustomFieldRendersTheResolvedCurrency(): void
    {
        $output = $this->renderFeature(CustomFieldTypes::PRICE, [
            'content' => [['currencyId' => Uuid::randomHex(), 'net' => 10.0, 'gross' => 11.9, 'linked' => true]],
            'display' => 11.9,
        ]);

        static::assertStringContainsString('Material:', $output);
        static::assertStringContainsString('11.9', $output);
    }

    /**
     * Line items of orders placed before select, entity and price custom fields were resolved carry
     * no display value, so there is nothing to render for them.
     */
    public function testSelectCustomFieldWithoutADisplayValueIsNotRendered(): void
    {
        $output = $this->renderFeature(CustomFieldTypes::SELECT, ['content' => ['oak']]);

        static::assertStringNotContainsString('blog-feature-list-item', $output);
    }

    public function testUnsupportedCustomFieldTypeIsNotRendered(): void
    {
        $output = $this->renderFeature(CustomFieldTypes::JSON, ['content' => ['foo' => 'bar']]);

        static::assertStringNotContainsString('blog-feature-list-item', $output);
    }

    /**
     * @param array<string, mixed> $value
     */
    private function renderFeature(string $type, array $value): string
    {
        $context = $this->createChannelContext();

        return FrontendTwigRenderer::render(static::getContainer(), '@Frontend/frontend/component/blog/feature/list.html.twig', [
            'context' => $context,
            'lineItem' => new LineItem(Uuid::randomHex(), LineItem::BLOG_LINE_ITEM_TYPE),
            'features' => [
                [
                    'label' => 'Material',
                    'value' => [...['id' => Uuid::randomHex(), 'type' => $type], ...$value],
                    'type' => BlogFeatureSetDefinition::TYPE_BLOG_CUSTOM_FIELD,
                ],
            ],
        ], $context);
    }
}
