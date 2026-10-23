<?php declare(strict_types=1);

namespace Contena\Tests\Examples;

use Contena\Core\Content\Blog\Extension\BlogListingCriteriaExtension;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class BlogListingCriteriaExtensionExample implements EventSubscriberInterface
{
    public function __construct(
        // you can inject your own services
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'blog.listing.criteria.post' => 'modifyCriteria',
        ];
    }

    public function modifyCriteria(BlogListingCriteriaExtension $event): void
    {
        $criteria = $event->criteria;

        // Modify criteria here as needed
        $criteria->resetFilters();

        $event->result = $criteria;
    }
}
