<?php declare(strict_types=1);

namespace Contena\Administration\Controller;

use Contena\Administration\Framework\Routing\AdministrationRouteScope;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\BlogAvailableFilter;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Contena\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Util\Random;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [AdministrationRouteScope::ID]])]
class AdminContentStreamController extends AbstractController
{
    /**
     * @internal
     *
     * @param ChannelRepository<BlogCollection> $channelBlogRepository
     */
    public function __construct(
        private readonly BlogDefinition $blogDefinition,
        private readonly ChannelRepository $channelBlogRepository,
        private readonly ChannelContextServiceInterface $channelContextService,
        private readonly RequestCriteriaBuilder $criteriaBuilder
    ) {
    }

    #[Route(path: '/api/_admin/blog-stream-preview/{channelId}', name: 'api.admin.blog-stream-preview', methods: ['POST'])]
    public function contentStreamPreview(string $channelId, Request $request, Context $context): JsonResponse
    {
        $channelContext = $this->channelContextService->get(
            new ChannelContextServiceParameters(
                $channelId,
                Random::getAlphanumericString(32),
                $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID),
                $context->getCurrencyId()
            )
        );

        if ($request->request->all('ids') === []) {
            $request->request->remove('ids');
        }

        $criteria = $this->criteriaBuilder->handleRequest(
            $request,
            new Criteria(),
            $this->blogDefinition,
            $context
        );

        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('options.group');

        $availableFilter = new BlogAvailableFilter($channelId, BlogVisibilityDefinition::VISIBILITY_ALL);
        $queries = $availableFilter->getQueries();
        // remove query for active field as we also want to preview inactive blogs
        array_pop($queries);
        $availableFilter->assign(['queries' => $queries]);
        $criteria->addFilter($availableFilter);

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        // Mirror the frontend listing's variant grouping so the preview reflects the blog stream's
        // "display as group" setting. It is read from the query string to keep the Criteria request body
        // (and therefore its schema) untouched.
        if ($request->query->getBoolean('displayAsGroup')) {
            $criteria->addGroupField(new FieldGrouping('displayGroup'));
            $criteria->addFilter(new NotEqualsFilter('displayGroup', null));
        }

        $previewResult = $this->channelBlogRepository->search($criteria, $channelContext);

        return new JsonResponse($previewResult);
    }
}
