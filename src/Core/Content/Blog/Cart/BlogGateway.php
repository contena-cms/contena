<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cart;

use Contena\Core\Content\Blog\Events\BlogGatewayCriteriaEvent;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class BlogGateway implements BlogGatewayInterface
{
    /**
     * @internal
     *
     * @param ChannelRepository<BlogCollection> $repository
     */
    public function __construct(
        private readonly ChannelRepository $repository,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @param list<string> $ids
     */
    public function get(array $ids, ChannelContext $context): BlogCollection
    {
        $criteria = new Criteria($ids);
        $criteria->setTitle('cart::blogs');
        $criteria->addAssociation('cover.media');
        $criteria->addAssociation('options.group');
        $criteria->addAssociation('featureSet');
        $criteria->addAssociation('properties.group');

        $this->eventDispatcher->dispatch(
            new BlogGatewayCriteriaEvent($ids, $criteria, $context)
        );

        return $this->repository->search($criteria, $context)->getEntities();
    }
}
