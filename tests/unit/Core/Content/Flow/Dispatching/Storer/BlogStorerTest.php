<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Event\UserRegisterEvent;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\BlogStorer;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Channel\Review\Event\ReviewFormEvent;
use Contena\Core\Content\Shared\MailFlow\DataProvider\BlogProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\BlogAware;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(BlogStorer::class)]
class BlogStorerTest extends TestCase
{
    private BlogStorer $storer;

    private Stub&BlogProvider $blogProvider;

    protected function setUp(): void
    {
        $this->blogProvider = static::createStub(BlogProvider::class);

        $this->storer = $this->createStorer($this->blogProvider);
    }

    public function testStoreWithAware(): void
    {
        $blog = new BlogEntity();
        $blog->setId('blog-id');

        $event = new ReviewFormEvent(Context::createDefaultContext(), '', new MailRecipientStruct([]), new DataBag(), 'blog-id', '', $blog);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(BlogAware::BLOG_ID, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = static::createStub(UserRegisterEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(BlogAware::BLOG_ID, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['blogId' => 'test_id']);

        $this->storer->restore($storable);

        static::assertArrayHasKey('blog', $storable->data());
    }

    public function testRestoreEmptyStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext());

        $this->storer->restore($storable);

        static::assertCount(0, $storable->data());
    }

    public function testLazyLoadEntity(): void
    {
        $blogProvider = $this->createMock(BlogProvider::class);
        $storer = $this->createStorer($blogProvider);

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['blogId' => 'id'], []);
        $storer->restore($storable);
        $entity = new BlogEntity();
        $entity->setId('id');

        $blogProvider->expects($this->once())->method('getData')->willReturn($entity);
        $res = $storable->getData('blog');

        static::assertSame($res, $entity);
    }

    public function testLazyLoadNullEntity(): void
    {
        $blogProvider = $this->createMock(BlogProvider::class);
        $storer = $this->createStorer($blogProvider);

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['blogId' => 'id'], []);
        $storer->restore($storable);
        $blogProvider->expects($this->once())->method('getData')->willReturn(null);

        $res = $storable->getData('blog');

        static::assertNull($res);
    }

    public function testLazyLoadNullId(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['blogId' => null], []);
        $this->storer->restore($storable);
        $blog = $storable->getData('blog');

        static::assertNull($blog);
    }

    private function createStorer(BlogProvider $blogProvider): BlogStorer
    {
        return new BlogStorer(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            $blogProvider,
        );
    }
}
