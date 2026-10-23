<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Mcp\Scenario;

use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Integration\Builder\Order\OrderBuilder;
use Contena\Core\Test\Integration\Builder\User\UserBuilder;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class UserInsightsScenarioTest extends McpScenarioTestCase
{
    public function testUS25OneStarReviews(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $blog = (new BlogBuilder($ids, 'reviewed-blog'))
            ->price(10.00)
            ->visibility(TestDefaults::CHANNEL)
            ->build();

        static::getContainer()->get('blog.repository')->create([$blog], $context);

        $channelId = TestDefaults::CHANNEL;
        $user = (new UserBuilder($ids, 'reviewer'))
            ->add('email', 'mcp-us25-' . Uuid::randomHex() . '@example.com')
            ->add('password', TestDefaults::HASHED_PASSWORD)
            ->build();

        static::getContainer()->get('user.repository')->create([$user], $context);

        $now = new \DateTimeImmutable();

        foreach ([1, 5, 1] as $i => $points) {
            $reviewId = Uuid::randomHex();

            static::getContainer()->get('blog_review.repository')->create([
                [
                    'id' => $reviewId,
                    'blogId' => $ids->get('reviewed-blog'),
                    'userId' => $ids->get('reviewer'),
                    'channelId' => $channelId,
                    'languageId' => $context->getLanguageId(),
                    'title' => 'Review ' . $i,
                    'content' => 'Test review content',
                    'externalUser' => 'Test',
                    'points' => $points,
                    'status' => true,
                ],
            ], $context);
        }

        $lastMonth = $now->modify('-30 days')->format(\DATE_ATOM);

        $output = ($this->entitySearchTool)(
            entity: 'blog_review',
            criteria: json_encode([
                'filter' => [
                    ['type' => 'equals', 'field' => 'points', 'value' => 1],
                    ['type' => 'range', 'field' => 'createdAt', 'parameters' => ['gte' => $lastMonth]],
                ],
            ], \JSON_THROW_ON_ERROR),
        );

        $data = $this->decodeToolOutput($output);

        static::assertGreaterThanOrEqual(2, \count($data['data']));

        foreach ($data['data'] as $review) {
            static::assertSame(1.0, $review['points']);
        }
    }

    public function testUS26InactiveUsers(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $sixMonthsAgo = (new \DateTimeImmutable())->modify('-7 months')->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $recentDate = (new \DateTimeImmutable())->modify('-1 month')->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $inactiveUser = (new UserBuilder($ids, 'inactive'))
            ->add('email', 'mcp-us26-inactive-' . Uuid::randomHex() . '@example.com')
            ->add('password', TestDefaults::HASHED_PASSWORD)
            ->add('lastOrderDate', $sixMonthsAgo)
            ->build();

        $activeUser = (new UserBuilder($ids, 'active'))
            ->add('email', 'mcp-us26-active-' . Uuid::randomHex() . '@example.com')
            ->add('password', TestDefaults::HASHED_PASSWORD)
            ->add('lastOrderDate', $recentDate)
            ->build();

        $context->scope(Context::SYSTEM_SCOPE, function (Context $systemContext) use ($inactiveUser, $activeUser): void {
            static::getContainer()->get('user.repository')->create([$inactiveUser, $activeUser], $systemContext);
        });

        $cutoff = (new \DateTimeImmutable())->modify('-6 months')->format(\DATE_ATOM);

        $output = ($this->entitySearchTool)(
            entity: 'user',
            criteria: json_encode([
                'filter' => [
                    ['type' => 'range', 'field' => 'lastOrderDate', 'parameters' => ['lte' => $cutoff]],
                ],
            ], \JSON_THROW_ON_ERROR),
        );

        $data = $this->decodeToolOutput($output);
        $foundIds = array_column($data['data'], 'id');

        static::assertContains($ids->get('inactive'), $foundIds, 'Inactive user should appear');
        static::assertNotContains($ids->get('active'), $foundIds, 'Active user should not appear');
    }

    public function testUS27AverageOrderValue(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $amounts = [100.0, 200.0, 300.0];

        foreach ($amounts as $i => $amount) {
            $orderNumber = 'MCP-US27-' . $i . '-' . Uuid::randomHex();
            $email = 'mcp-us27-' . $i . '-' . Uuid::randomHex() . '@example.com';

            $user = (new UserBuilder($ids, 'cust-us27-' . $i))
                ->add('email', $email)
                ->add('password', TestDefaults::HASHED_PASSWORD)
                ->build();

            static::getContainer()->get('user.repository')->create([$user], $context);

            $orderIds = new IdsCollection();
            $order = (new OrderBuilder($orderIds, $orderNumber))
                ->price($amount)
                ->add('orderDateTime', (new \DateTimeImmutable('2025-03-' . \sprintf('%02d', $i + 10)))->format(Defaults::STORAGE_DATE_TIME_FORMAT))
                ->add('orderUser', [
                    'id' => $orderIds->get('orderUser'),
                    'userId' => $ids->get('cust-us27-' . $i),
                    'firstName' => 'Avg',
                    'lastName' => 'Test',
                    'email' => $email,
                ])
                ->addAddress('billing-address')
                ->addTransaction('transaction', ['amount' => $amount])
                ->build();

            static::getContainer()->get('order.repository')->upsert([$order], $context);
        }

        $output = ($this->entityAggregateTool)(
            entity: 'order',
            aggregations: json_encode([
                ['type' => 'avg', 'name' => 'avgOrderValue', 'field' => 'amountTotal'],
            ], \JSON_THROW_ON_ERROR),
            filters: json_encode([
                ['type' => 'range', 'field' => 'orderDateTime', 'parameters' => [
                    'gte' => '2025-03-01T00:00:00+00:00',
                    'lte' => '2025-03-31T23:59:59+00:00',
                ]],
            ], \JSON_THROW_ON_ERROR),
        );

        $data = $this->decodeToolOutput($output);

        static::assertArrayHasKey('aggregations', $data['data']);
        static::assertArrayHasKey('avgOrderValue', $data['data']['aggregations']);
        static::assertGreaterThan(0, $data['data']['aggregations']['avgOrderValue']['avg']);
    }
}
