<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Mcp\Scenario;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class PromotionsMarketingScenarioTest extends McpScenarioTestCase
{
    public function testUS20ActivePromotions(): void
    {
        $context = Context::createDefaultContext();

        $promotionId = Uuid::randomHex();
        $now = new \DateTimeImmutable();

        static::getContainer()->get('promotion.repository')->create([
            [
                'id' => $promotionId,
                'name' => 'MCP Test Promo US20',
                'active' => true,
                'useCodes' => false,
                'useSetGroups' => false,
                'channels' => [],
                'validFrom' => $now->modify('-1 day')->format(\DATE_ATOM),
                'validUntil' => $now->modify('+7 days')->format(\DATE_ATOM),
            ],
        ], $context);

        $output = ($this->entitySearchTool)(
            entity: 'promotion',
            criteria: json_encode([
                'filter' => [
                    ['type' => 'equals', 'field' => 'active', 'value' => true],
                    ['type' => 'range', 'field' => 'validFrom', 'parameters' => ['lte' => $now->format(\DATE_ATOM)]],
                    ['type' => 'range', 'field' => 'validUntil', 'parameters' => ['gte' => $now->format(\DATE_ATOM)]],
                ],
            ], \JSON_THROW_ON_ERROR),
        );

        $data = $this->decodeToolOutput($output);

        $found = false;
        foreach ($data['data'] as $promo) {
            if ($promo['id'] === $promotionId) {
                $found = true;

                break;
            }
        }

        static::assertTrue($found, 'Active promotion should appear in search results');
    }

    public function testUS21NewsletterSubscriberCount(): void
    {
        $context = Context::createDefaultContext();

        $channelId = $this->getChannelId();

        for ($i = 0; $i < 3; ++$i) {
            static::getContainer()->get('newsletter_recipient.repository')->create([
                [
                    'id' => Uuid::randomHex(),
                    'email' => 'mcp-us21-' . $i . '-' . Uuid::randomHex() . '@example.com',
                    'status' => 'optIn',
                    'hash' => Uuid::randomHex(),
                    'channelId' => $channelId,
                    'languageId' => $context->getLanguageId(),
                ],
            ], $context);
        }

        $output = ($this->entityAggregateTool)(
            entity: 'newsletter_recipient',
            aggregations: json_encode([
                ['type' => 'count', 'name' => 'total', 'field' => 'id'],
            ], \JSON_THROW_ON_ERROR),
        );

        $data = $this->decodeToolOutput($output);

        static::assertArrayHasKey('aggregations', $data['data']);
        static::assertGreaterThanOrEqual(3, $data['data']['aggregations']['total']['count']);
    }

    private function getChannelId(): string
    {
        $result = static::getContainer()->get('channel.repository')
            ->searchIds(new Criteria(), Context::createDefaultContext());

        return $result->firstId() ?? throw new \RuntimeException('No sales channel found');
    }
}
