<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Storer;

use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Shared\MailFlow\DataProvider\BlogProvider;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\BlogAware;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class BlogStorer extends FlowStorer
{
    /**
     * @internal
     *
     * @param EntityRepository<BlogCollection> $blogRepository
     */
    public function __construct(
        private readonly EntityRepository $blogRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly BlogProvider $blogProvider,
    ) {
    }

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

        if (!Feature::isActive('v6.8.0.0')) {
            $criteria = $this->blogProvider->getCriteria($id, $storableFlow->getContext());

            $event = new BeforeLoadStorableFlowDataEvent(
                BlogDefinition::ENTITY_NAME,
                $criteria,
                $storableFlow->getContext(),
            );

            $this->dispatcher->dispatch($event, $event->getName());

            $blog = $this->blogRepository->search($criteria, $storableFlow->getContext())->getEntities()->get($id);

            if ($blog) {
                return $blog;
            }

            return null;
        }

        return $this->blogProvider->getData($id, $storableFlow->getContext());
    }
}
