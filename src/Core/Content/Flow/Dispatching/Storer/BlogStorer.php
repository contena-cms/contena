<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Storer;

use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Shared\MailFlow\DataProvider\BlogProvider;
use Contena\Core\Framework\Event\BlogAware;
use Contena\Core\Framework\Event\FlowEventAware;

class BlogStorer extends FlowStorer
{
    /**
     * @internal
     */
    public function __construct(private readonly BlogProvider $blogProvider)
    {
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof BlogAware || isset($stored[BlogAware::BLOG_ID])) {
            return $stored;
        }

        $stored[BlogAware::BLOG_ID] = $event->getBlogId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(BlogAware::BLOG_ID)) {
            return;
        }

        $storable->lazy(
            BlogAware::BLOG,
            $this->lazyLoad(...)
        );
    }

    private function lazyLoad(StorableFlow $storableFlow): ?BlogEntity
    {
        $id = $storableFlow->getStore(BlogAware::BLOG_ID);
        if ($id === null) {
            return null;
        }

        return $this->blogProvider->getData($id, $storableFlow->getContext());
    }
}
