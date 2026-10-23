<?php declare(strict_types=1);

namespace Contena\Tests\Examples;

use Contena\Core\Content\Blog\Extension\BlogSearchRouteExtension;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Search\BlogSearchRouteResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Resolves the blog search yourself — e.g. against an external search service —
 * instead of the core listing-loader based search.
 */
readonly class BlogSearchRouteExample implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            BlogSearchRouteExtension::NAME . '.pre' => 'replace',
        ];
    }

    public function replace(BlogSearchRouteExtension $event): void
    {
        // The request is exposed through the public properties:
        // $event->request, $event->context, $event->criteria

        $result = new BlogListingResult(
            BlogDefinition::ENTITY_NAME,
            0,
            new BlogCollection(),
            null,
            $event->criteria,
            $event->context->getContext(),
        );

        $event->result = new BlogSearchRouteResponse($result);

        // stop propagation so the core blog search is skipped
        $event->stopPropagation();
    }
}
