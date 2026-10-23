<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Channel\Review;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\BufferedFlowExecutor;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewSaveRoute;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

/**
 * @internal
 */
#[Group('store-api')]
class BlogReviewSaveRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->setVisibilities();
    }

    public function testRequiresLogin(): void
    {
        $this->browser->request('POST', $this->getUrl());

        $response = $this->browser->getResponse();

        static::assertSame(403, $response->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame($response['errors'][0]['code'], RoutingException::CUSTOMER_NOT_LOGGED_IN_CODE);
    }

    #[DataProvider('provideContentData')]
    public function testCreate(string $content, string $expectedContent): void
    {
        $this->login($this->browser);

        $this->assertReviewCount(0);

        $this->browser->request('POST', $this->getUrl(), [
            'title' => 'Lorem ipsum dolor sit amet',
            'content' => $content,
            'points' => 5,
        ]);

        $response = $this->browser->getResponse();

        static::assertSame(204, $response->getStatusCode(), print_r($this->browser->getResponse()->getContent(), true));

        $this->assertReviewCount(1);

        $this->assertReviewContent($expectedContent);
    }

    public function testCreateRejectsContentThatIsEmptyAfterSanitizing(): void
    {
        $this->login($this->browser);

        $this->assertReviewCount(0);

        $this->browser->request('POST', $this->getUrl(), [
            'title' => 'Lorem ipsum dolor sit amet',
            'content' => '<script>alert("Lorem ipsum dolor sit amet, consetetur sadipscing elitr")</script>',
            'points' => 5,
        ]);

        $response = $this->browser->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), print_r($response->getContent(), true));

        $errors = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['errors'];

        static::assertSame(NotBlank::IS_BLANK_ERROR, $errors[0]['code']);
        static::assertStringEndsWith('/content', $errors[0]['source']['pointer']);

        $this->assertReviewCount(0);
    }

    /**
     * @param array<string, mixed> $points
     */
    #[DataProvider('provideMissingPoints')]
    public function testCreateRequiresPoints(array $points): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $this->login($this->browser);

        $this->browser->request(
            'POST',
            $this->getUrl(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'title' => 'Lorem ipsum dolor sit amet',
                'content' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna',
                ...$points,
            ], \JSON_THROW_ON_ERROR)
        );

        $response = $this->browser->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), print_r($response->getContent(), true));

        $errors = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['errors'];

        static::assertCount(1, $errors);
        static::assertSame('VIOLATION::IS_BLANK_ERROR', $errors[0]['code']);
        static::assertSame('/points', $errors[0]['source']['pointer']);

        $this->assertReviewCount(0);
    }

    public function testCreateWithoutPointsSavesTheReviewBeforeTheMajor(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $this->login($this->browser);

        $this->browser->request('POST', $this->getUrl(), [
            'title' => 'Lorem ipsum dolor sit amet',
            'content' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna',
        ]);

        $response = $this->browser->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), print_r($response->getContent(), true));

        $this->assertReviewCount(1);
    }

    #[DataProvider('providePointsInRange')]
    public function testRepositoryAcceptsPointsFromOneToFive(float $points): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $this->createReviewThroughRepository($points);

        $this->assertReviewCount(1);
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function providePointsInRange(): iterable
    {
        yield 'lowest rating' => [1.0];
        yield 'highest rating' => [5.0];
    }

    #[DataProvider('providePointsOutOfRange')]
    public function testRepositoryRejectsPointsOutsideOneToFive(float $points): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        try {
            $this->createReviewThroughRepository($points);
            static::fail('Points outside 1 to 5 have to be rejected');
        } catch (WriteException $exception) {
            $violation = $exception->getExceptions()[0] ?? null;

            static::assertInstanceOf(WriteConstraintViolationException::class, $violation);
            static::assertSame(Range::NOT_IN_RANGE_ERROR, $violation->getViolations()->get(0)->getCode());
        }

        $this->assertReviewCount(0);
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function providePointsOutOfRange(): iterable
    {
        yield 'zero' => [0.0];
        yield 'below one' => [0.5];
        yield 'above five' => [5.5];
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideMissingPoints(): iterable
    {
        yield 'points omitted' => [[]];
        yield 'points null' => [['points' => null]];
    }

    public function testUpdate(): void
    {
        $this->login($this->browser);

        $this->assertReviewCount(0);

        $id = Uuid::randomHex();

        $this->browser->request('POST', $this->getUrl(), [
            'title' => 'Lorem ipsum dolor sit amet',
            'content' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna',
            'points' => 5,
        ]);

        $response = $this->browser->getResponse();

        static::assertSame(204, $response->getStatusCode(), print_r($this->browser->getResponse()->getContent(), true));

        $this->assertReviewCount(1);

        $this->browser->request('POST', $this->getUrl(), [
            'id' => $id,
            'title' => 'Lorem ipsum dolor sit amet',
            'content' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna',
            'points' => 5,
        ]);
        $this->assertReviewCount(1);
    }

    public function testValidation(): void
    {
        $this->login($this->browser);

        $this->browser->request('POST', $this->getUrl());

        $response = $this->browser->getResponse();

        static::assertSame(400, $response->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame($response['errors'][0]['source']['pointer'], '/title');
        static::assertSame($response['errors'][1]['source']['pointer'], '/content');

        if (Feature::isActive('v6.8.0.0')) {
            static::assertSame($response['errors'][2]['source']['pointer'], '/points');
        }
    }

    public function testUserValidation(): void
    {
        $this->login($this->browser);

        $this->assertReviewCount(0);

        $id = Uuid::randomHex();

        // Create review
        $this->browser->request('POST', $this->getUrl(), [
            'id' => $id,
            'title' => 'Lorem ipsum dolor sit amet',
            'content' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna',
            'points' => 5,
        ]);

        // Re-login as another admin_user
        $this->login($this->browser);

        // Try to use the id from previous review which is not attached to this user
        $this->browser->request('POST', $this->getUrl(), [
            'id' => $id,
            'title' => 'Lorem ipsum dolor sit amet',
            'content' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna',
            'points' => 5,
        ]);

        $response = $this->browser->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('VIOLATION::ENTITY_DOES_NOT_EXISTS', $content['errors'][0]['code']);
    }

    public function testMailIsSent(): void
    {
        $userId = $this->createUser();
        $channelContext = $this->createChannelContext([], [
            ChannelContextService::CUSTOMER_ID => $userId,
        ]);

        $caughtEvent = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            MailBeforeSentEvent::class,
            static function (MailBeforeSentEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            }
        );

        $data = new RequestDataBag([
            'title' => 'Lorem ipsum dolor sit amet',
            'content' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna',
            'points' => 3,
        ]);
        static::getContainer()->get(BlogReviewSaveRoute::class)->save(
            $this->ids->get('blog'),
            $data,
            $channelContext
        );
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        static::assertInstanceOf(MailBeforeSentEvent::class, $caughtEvent);
        $bodyText = $caughtEvent->getMessage()->getTextBody();
        $bodyText = \is_string($bodyText) ? $bodyText : '';
        static::assertStringContainsString($data->get('title'), $bodyText);
        static::assertStringContainsString($data->get('content'), $bodyText);
        static::assertStringContainsString($this->ids->get('unique-name'), $bodyText);
    }

    public static function provideContentData(): \Generator
    {
        yield 'simple' => [
            'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna',
            'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna',
        ];
        yield 'html' => [
            '<a href="https://localhost">Lorem ipsum dolor sit amet, consetetur sadipscing elitr</a>',
            'Lorem ipsum dolor sit amet, consetetur sadipscing elitr',
        ];
        yield 'javascript' => [
            '<script>alert("foo")</script><p>foo</p><script>alert("foo")</script>',
            'foo',
        ];
        yield 'javascript with attributes' => [
            '<script type="text/javascript">alert("foo")</script><p>foo</p><script>alert("foo")</script>',
            'foo',
        ];
        yield 'javascript with attributes and spaces' => [
            '<script type = "text/javascript">alert("foo")</script><p>foo</p><script>alert("foo")</script>',
            'foo',
        ];
    }

    private function assertReviewCount(int $expected): void
    {
        $count = (int) static::getContainer()
            ->get(Connection::class)
            ->fetchOne('SELECT COUNT(*) FROM blog_review WHERE blog_id = :id', ['id' => Uuid::fromHexToBytes($this->ids->get('blog'))]);

        static::assertSame($expected, $count);
    }

    private function createData(): void
    {
        $blog = [
            'id' => $this->ids->create('blog'),
            'manufacturer' => ['id' => $this->ids->create('manufacturer-'), 'name' => 'test-'],
            'blogNumber' => $this->ids->get('blog'),
            'name' => $this->ids->get('unique-name'),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'active' => true,
        ];

        static::getContainer()->get('blog.repository')
            ->create([$blog], Context::createDefaultContext());
    }

    private function setVisibilities(): void
    {
        $update = [
            [
                'id' => $this->ids->get('blog'),
                'visibilities' => [
                    ['channelId' => $this->ids->get('sales-channel'), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ];
        static::getContainer()->get('blog.repository')
            ->update($update, Context::createDefaultContext());
    }

    private function createReviewThroughRepository(float $points): void
    {
        static::getContainer()->get('blog_review.repository')->create([[
            'blogId' => $this->ids->get('blog'),
            'channelId' => $this->ids->get('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'externalUser' => 'Max',
            'title' => 'Lorem ipsum dolor sit amet',
            'content' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr',
            'points' => $points,
        ]], Context::createDefaultContext());
    }

    private function getUrl(): string
    {
        return '/store-api/blog/' . $this->ids->get('blog') . '/review';
    }

    private function assertReviewContent(string $expectedContent): void
    {
        $content = static::getContainer()
            ->get(Connection::class)
            ->fetchOne('SELECT content FROM blog_review WHERE blog_id = :id', ['id' => Uuid::fromHexToBytes($this->ids->get('blog'))]);

        static::assertSame($expectedContent, $content);
    }
}
