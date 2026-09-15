<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Comment;

use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Channel\Comment\Event\BlogCommentFormEvent;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BlogCommentFormEvent::class)]
class BlogCommentFormEventTest extends TestCase
{
    public function testInstanceAndFlowValues(): void
    {
        $context = Context::createDefaultContext();
        $blog = new BlogEntity();
        $blog->setId('9a5d9343c1aa4ec2b544dd77e7f398e8');
        $data = new DataBag(['content' => 'A useful comment']);
        $event = new BlogCommentFormEvent(
            $context,
            'channel-id',
            new MailRecipientStruct(['member@example.com' => 'Member']),
            $data,
            $blog->getId(),
            'member-id',
            $blog,
        );

        static::assertSame($context, $event->getContext());
        static::assertSame('channel-id', $event->getChannelId());
        static::assertSame($blog, $event->getBlog());
        static::assertSame($data->all(), $event->getCommentFormData());
        static::assertSame(BlogCommentFormEvent::EVENT_NAME, $event->getName());

        $storer = new ScalarValuesStorer();
        $stored = $storer->store($event, []);
        $flow = new StorableFlow('blog-comment', $context, $stored);
        $storer->restore($flow);

        static::assertSame($data->all(), $flow->data()['commentFormData']);
    }
}
