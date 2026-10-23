<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Review;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewCollection;
use Contena\Core\Content\Blog\Extension\BlogReviewSaveRouteExtension;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\Review\Event\ReviewFormEvent;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewSaveRoute;
use Contena\Core\Content\Shared\MailFlow\DataProvider\BlogProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\Channel\NoContentResponse;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(BlogReviewSaveRoute::class)]
class BlogReviewSaveRouteTest extends TestCase
{
    /**
     * @var Stub&EntityRepository<BlogReviewCollection>
     */
    private Stub&EntityRepository $repository;

    private Stub&DataValidator $validator;

    private StaticSystemConfigService $config;

    private Stub&EventDispatcherInterface $eventDispatcher;

    private Stub&BlogProvider $blogProvider;

    private BlogReviewSaveRoute $route;

    protected function setUp(): void
    {
        $this->repository = static::createStub(EntityRepository::class);
        $this->validator = static::createStub(DataValidator::class);
        $this->config = new StaticSystemConfigService([
            'test' => [
                'core.listing.showReview' => true,
                'core.basicInformation.email' => 'noreply@example.com',
            ],
            'testReviewNotActive' => [
                'core.listing.showReview' => false,
                'core.basicInformation.email' => 'noreply@example.com',
            ],
        ]);
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $this->blogProvider = static::createStub(BlogProvider::class);

        $this->route = new BlogReviewSaveRoute(
            $this->repository,
            $this->validator,
            $this->config,
            $this->eventDispatcher,
            $this->blogProvider,
            new ExtensionDispatcher(new EventDispatcher())
        );
    }

    public function testSave(): void
    {
        $id = Uuid::randomHex();
        $blogId = Uuid::randomHex();
        $data = new RequestDataBag([
            'id' => $id,
            'title' => 'foo',
            'content' => 'bar',
            'points' => 3,
        ]);

        $channelContext = $this->createMock(ChannelContext::class);
        $context = Context::createDefaultContext();
        $user = new UserEntity();
        $user->setId(Uuid::randomHex());
        $user->setFirstName('Max');
        $user->setLastName('Mustermann');
        $user->setEmail('foo@example.com');
        $channel = new ChannelEntity();
        $channel->setId('test');
        $blog = new BlogEntity();
        $blog->setId($blogId);

        $channelContext->expects($this->once())->method('getUser')->willReturn($user);
        $channelContext->expects($this->exactly(1))->method('getChannelId')->willReturn($channel->getId());
        $channelContext->expects($this->exactly(1))->method('getLanguageId')->willReturn($context->getLanguageId());
        $channelContext->expects($this->exactly(4))->method('getContext')->willReturn($context);

        $validator = $this->createMock(DataValidator::class);
        $validator->expects($this->once())->method('getViolations')->willReturn(new ConstraintViolationList());

        $blogProvider = $this->createMock(BlogProvider::class);
        $blogProvider->expects($this->once())->method('getData')->with($blogId, $context)->willReturn($blog);

        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('upsert')
            ->with([
                [
                    'blogId' => $blogId,
                    'userId' => $user->getId(),
                    'channelId' => $channel->getId(),
                    'languageId' => $context->getLanguageId(),
                    'externalUser' => $user->getFirstName(),
                    'externalEmail' => $user->getEmail(),
                    'title' => $data->get('title'),
                    'content' => $data->get('content'),
                    'points' => $data->get('points'),
                    'status' => false,
                    'id' => $data->get('id'),
                ],
            ], $context);

        $event = new ReviewFormEvent(
            $context,
            $channel->getId(),
            new MailRecipientStruct(['foo@example.com' => 'Max Mustermann']),
            new RequestDataBag([
                'title' => 'foo',
                'content' => 'bar',
                'points' => 3,
                'name' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
                'userId' => $user->getId(),
                'blogId' => $blogId,
                'id' => $id,
            ]),
            $blogId,
            $user->getId(),
            $blog
        );

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($event, ReviewFormEvent::EVENT_NAME);

        $route = new BlogReviewSaveRoute(
            $repository,
            $validator,
            $this->config,
            $eventDispatcher,
            $blogProvider,
            new ExtensionDispatcher(new EventDispatcher())
        );

        $route->save($blogId, $data, $channelContext);
    }

    public function testSaveReviewDeactivated(): void
    {
        $ids = new IdsCollection();

        $this->expectExceptionObject(BlogException::reviewNotActive());

        $channelContext = $this->createMock(ChannelContext::class);
        $channelContext->expects($this->exactly(1))->method('getChannelId')->willReturn('testReviewNotActive');

        $this->route->save(
            $ids->get('blogId'),
            new RequestDataBag(['test' => 'test']),
            $channelContext,
        );
    }

    public function testPublishesExtension(): void
    {
        $blogId = Uuid::randomHex();
        $data = new RequestDataBag(['title' => 'Great blog', 'content' => 'Fits well and the material feels durable.', 'points' => 5]);
        $context = Generator::generateChannelContext();
        $response = new NoContentResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('blog-review-save-route.save.pre', static function (BlogReviewSaveRouteExtension $extension) use ($blogId, $data, $context, $response): void {
            static::assertSame(['blogId' => $blogId, 'data' => $data, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new BlogReviewSaveRoute(
            static::createStub(EntityRepository::class),
            static::createStub(DataValidator::class),
            static::createStub(SystemConfigService::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(BlogProvider::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->save($blogId, $data, $context));
    }
}
