<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Review\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Channel\Review\Event\ReviewFormEvent;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Feature\FeatureException;
use Contena\Core\Framework\Validation\DataBag\DataBag;

/**
 * @internal
 */
#[CoversClass(ReviewFormEvent::class)]
class ReviewFormEventTest extends TestCase
{
    public function testInstance(): void
    {
        $context = Context::createDefaultContext();
        $channelId = 'foo';
        $mailRecipientStruct = new MailRecipientStruct(['foo' => 'bar']);
        $data = new DataBag(['baz']);
        $blogId = 'bar';
        $userId = 'bar';
        $blog = new BlogEntity();
        $blog->setId($blogId);

        $event = new ReviewFormEvent($context, $channelId, $mailRecipientStruct, $data, $blogId, $userId, $blog);

        static::assertSame($context, $event->getContext());
        static::assertSame($channelId, $event->getChannelId());
        static::assertSame($mailRecipientStruct, $event->getMailStruct());
        static::assertSame($data->all(), $event->getReviewFormData());
        static::assertSame($blogId, $event->getBlogId());
        static::assertSame($blog, $event->getBlog());
        static::assertSame($userId, $event->getUserId());
    }

    public function testScalarValuesCorrectly(): void
    {
        $blog = new BlogEntity();
        $blog->setId('blog-id');

        $event = new ReviewFormEvent(
            Context::createDefaultContext(),
            'sales-channel-id',
            new MailRecipientStruct(['foo' => 'bar']),
            new DataBag(['foo' => 'bar', 'bar' => 'baz']),
            'blog-id',
            'user-id',
            $blog
        );

        $storer = new ScalarValuesStorer();

        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);

        $storer->restore($flow);

        static::assertArrayHasKey('reviewFormData', $flow->data());
        static::assertSame(['foo' => 'bar', 'bar' => 'baz'], $flow->data()['reviewFormData']);
    }

    public function testConstructorRequiresBlogWhenFeatureActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Not passing $blog to ' . ReviewFormEvent::class . ' is deprecated and will be required in v6.8.0.'
        ));
        new ReviewFormEvent(
            Context::createDefaultContext(),
            'sales-channel-id',
            new MailRecipientStruct(['foo' => 'bar']),
            new DataBag(),
            'blog-id',
            'user-id'
        );
    }

    public function testDescribesItsFlowContract(): void
    {
        static::assertSame(ReviewFormEvent::EVENT_NAME, (new ReviewFormEvent(
            Context::createDefaultContext(),
            'sales-channel-id',
            new MailRecipientStruct(['foo' => 'bar']),
            new DataBag(),
            'blog-id',
            'user-id',
            new BlogEntity()
        ))->getName());
        static::assertSame(['reviewFormData', 'blog'], array_keys(ReviewFormEvent::getAvailableData()->toArray()));
    }
}
